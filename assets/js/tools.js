/**
 * NITM Javascript Tools
 * Tools which allow some generic functionality not provided by Bootstrap
 * © NITM 2014
 */

function Tools ()
{
	var self = this;
	this.id = 'tools';
	this.defaultInit = [
		'initVisibility',
		'initRemoveParent',
		'initDisableParent',
		'initCloneParent',
		'initBsMultipleModal',
		'initDynamicDropdown',
		'initDynamicValue',
		'initOffCanvasMenu',
		'initAutocompleteSelect',
		'initSubmitSelect',
		'initToolTips',
	];
	
	this.init = function (containerId) {
		this.coreInit(containerId);
	}
	
	this.coreInit = function (containerId) {
		this.defaultInit.map(function (method, key) {
			if(typeof self[method] == 'function')
				self[method](containerId);
		});
	}
	
	/**
	 * Submit a form on change of dropdown input
	 */
	this.initSubmitSelect = function (containerId) {
		//May not be necesary when using Bootstrap Nav menu
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);	
		container.find("[role~='changeSubmit']").map(function(e) {
			$(this).off('change');
			$(this).on('change', function (event) {
				window.location.replace($(this).val());
			});
		});
	}
	
	/**
	 * Use data attributes to load a URL into a container/element
	 */
	this.initVisibility = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		//enable hide/unhide functionality with optional data retrieval
		container.find("[role~='visibility']").map(function(e) {
			var _target = this;
			switch(_target.id != undefined)
			{
				case true:
				var events = $(this).data('events') != undefined ? $(this).data('events').split(',') : ['click'];
				$.each(events, function (index, eventName) {
					$(_target).off(eventName);
					if($(_target).data('no-animation'))
						var _callback = function (e) {
							self.visibility(_target)
						}
					else
						var _callback = function (e) {
							e.preventDefault();
							$.when(self.visibility(_target)).done(function () {
							});
						}
					if($(this).data('run-once'))
						$(_target).one(eventName, _callback);
					else
						$(_target).on(eventName, _callback);
				});
				break;
			}
		});
	}
	
	this.visibility = function (object, removeListener) {
		
		$($nitm).trigger('nitm-animate-submit-start', [object]);
		
		var _visSelf = this;
		var on = $(object).data('on');
		var getUrl = true;
		var url = !$(object).data('url') ? $(object).attr('href') : $(object).data('url');
		
		switch($(this).data('on') != undefined)
		{
			case true:
			if($(this).data('on').length == 0) getUrl = false;
			break;
		}
		
		var getRemote = function () {
			var basedOnGetUrl = (url != undefined) && (url != '#') && (url.length >= 2) && getUrl;
			var basedOnRemoteOnce = ($(object).data('remote-once') != undefined) ? (Boolean($(object).data('remote-once')) && !$(object).data('got-remote')) : true;
			return basedOnGetUrl && basedOnRemoteOnce;
		}
		
		if(getRemote())
		{
			this.target = $nitm.getObj($(object).data('id'));
			this.success = ($(object).data('success') != undefined) ? $(object).data('success') : null;
			this.url = $(object).data('url') ? $(object).data('url') : $(object).attr('href');
			var ret_val = $.ajax({
				url: url, 
				dataType: $(object).data('type') ? $(object).data('type') : 'html',
				complete: function (result) {
					$nitm.module('tools').replaceContents(result.responseText, object, _visSelf);
				}
			});
			$(object).data('got-remote', true);
		}
		
		$($nitm).trigger('nitm-animate-submit-stop', [object]);
		
		console.log(object);
		console.log($(object).data('id'));
		$nitm.handleVis($(object).data('id'));
		return false;
	}
	
	this.replaceContents = function (result, object, visibility) {
		if($(object).data('toggle')) {
			$.when($nitm.handleVis($(object).data('toggle'))).done(function () {
				self.evalScripts(result, function (responseText) {
					visibility.target.html(responseText);
				});
			});
		}
		else {
			self.evalScripts(result, function (responseText) {
				visibility.target.html(responseText);
			});
		}
	}
	
	/**
	 * Populate another dropdown with data from the current dropdown
	 */
	this.initDynamicDropdown = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);		
		container.find("[role~='dynamicDropdown']").map(function(e) {
			var id = $(this).data('id');
			switch(id != undefined)
			{
				case true:
				$(this).off('change');
				$(this).on('change', function (e) {
					e.preventDefault();
					var element = $nitm.getObj('#'+id);
					var url = $(this).data('url');
					switch((url != '#') && (url.length >= 2))
					{
						case true:
							element.removeAttr('disabled');
							element.empty();	
							var ret_val = $.get(url+$(this).find(':selected').val()).done( function (result) {
								var result = $.parseJSON(result);
								element.append( $('<option></option>').val('').html('Select value...') );
								if(typeof result == 'object')
								{
									$.each(result, function(val, text) {
										element.append( $('<option></option>').val(text.value).html(text.label) );
									});
								}
							}, 'json');
							break;
					}
					return ret_val;
				});
				break;
			}
		});
	}
	
	/**
	 * Set the value for an element using data attributes
	 */
	this.initDynamicValue = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		//enable hide/unhide functionality with optional data retrieval
		container.find("[role~='dynamicValue']").map(function(e) {
			switch(($(this).data('id') != undefined) || ($(this).data('type') != undefined))
			{
				case true:
				var _target = this;
				var events = $(this).data('events') != undefined ? $(this).data('events').split(',') : ['click'];
				$.each(events, function (index, eventName) {
					switch($(_target).data('run-once'))
					{
						case true:
						case 1:
						$(_target).one(eventName, function (e) {
							e.preventDefault();
							$.when(self.dynamicValue(_target)).done(function () {
							});
						});
						break;
						
						default:
						$(_target).on(eventName, function (e) {
							e.preventDefault();
							$.when(self.dynamicValue(_target)).done(function () {
							});
						});
						break;
					}
				});
				break;
			}
		});
	}
	
	this.dynamicValue = function (object) {
		
		$($nitm).trigger('nitm-animate-submit-start', [object]);
		
		var ret_val = null;
		var element = !$(object).data('id') ? $nitm.getObj(object) : $nitm.getObj($(object).data('id'));
		if(element.data('run-once') && (element.data('run-times') >= 1))
			return;
		var url = !$(object).data('url') ? $(object).attr('href') : $(object).data('url');
		var on = $(object).data('on');
		switch((url != '#') && (url.length >= 2))
		{
			case true:
			element.removeAttr('disabled');
			element.empty();	
			var selected = !$(object).find(':selected').val() ? '' : $(object).find(':selected').val();
			switch(on != undefined)
			{
				case true:
				if($(on).get(0) == undefined) return false;
				break;
			}
			switch($(object).data('type'))
			{
				case 'html':
				var ret_val = $.ajax({
					url: url+selected,
					type: ($(object).data('method') != undefined) ? $(object).data('method') : 'get', 
					dataType: 'html',
					complete: function (result) {
						self.evalScripts(result.responseText, function (responseText) {
							element.html(responseText);
						});
					}
				});
				break;
				
				case 'callback':
				eval("var callback = "+$(object).data('callback'));
				var ret_val = $.ajax({
					url: url+selected,
					type: ($(object).data('method') != undefined) ? $(object).data('method') : 'get',  
					dataType: 'json',
					complete: function (result) {
						callback(result, element.get(0));
					}
				});
				break;
				
				default:
				var ret_val = $.ajax({
					url: url+selected,
					type: ($(object).data('method') != undefined) ? $(object).data('method') : 'get',  
					dataType: 'text',
					complete: function (result) {
						element.val(result.responseText);
					}
				});
				break;
			}
			break;
		}
		element.data('run-times', 1);
		$($nitm).trigger('nitm-animate-submit-stop', [object]);;
		return ret_val; 
	}
	
	/**
	 * THis is used to evaluate remote js files returned in ajax calls
	 */
	this.evalScripts = function (text, callback, options) {
		var dom = $(text);
		//Need to find top level and nested scripts in returned text
		var scripts = dom.find('script');
		$.merge(scripts, dom.filter('script'));
		//Load remote scripts before ading content to DOM
		scripts.each(function(){
			if(this.src) {
				$.getScript(this.src);
				$(this).remove();
			}
		});
		if (typeof callback == 'function') {
			$(document).one('ajaxStop', function () {
				switch(options != undefined)
				{
					case true:
					var existing = (options.context == undefined) ? false : options.context.attr('id');
					break;
					
					default:
					var existing = false;
					break;
				}
				var existingWrapper = !existing ? false : $nitm.getObj(existing).find("[role='nitmToolsAjaxWrapper']").attr('id');
				switch(!existingWrapper)
				{
					case false:
					var wrapperId = existingWrapper;
					var wrapper = $('#'+wrapperId);
					wrapper.html('').html(dom.html());
					break;
					
					default:
					var wrapperId = 'nitm-tools-ajax-wrapper'+Date.now();
					var wrapper = $('<div id="'+wrapperId+'" role="nitmToolsAjaxWrapper">');
					wrapper.append(dom);
					break;
				}
				var contents = $('<div>').append(wrapper);
				//Execute basic init on new content
				(function () {
					return $.Deferred(function (deferred) {
						try {
							//Remove the scripts here so that they don't get run right away.
							contents.find('script').remove();
							callback(contents.html());
						} catch (error) {console.log(error)};
						deferred.resolve();
					}).promise();
				})().then(function () {
					self.coreInit(wrapperId);
					var scriptText = '';
					/*
					 *Now we can run the scripts that were not run.
					 *We'll collect them and then execute them all at once
					 */
					scripts.each(function(){
						if($(this).text()) {
							contents.append($(this));
							scriptText += $(this).text();
						}
					});
					eval(scriptText);
					delete scripts;
				});
			});
		}
	}
	
	/**
	 * Fix for handling slow loading remote js with pjax.
	 * We need to hook onto the before send function and not execute
	 * until the scripts have been loaded.
	 */
	this.pjaxAjaxStop = function () {
		$(document).on('pjax:beforeSend', function (event, xhr, options) {
			var success = options.success;
			options.success = function () {
				self.evalScripts(xhr.responseText, function (responseText) {
					success(responseText, status, xhr);
				}, options);
			}
		});
	};
	
	/**
	 * Remove the parent element up to a certain depth
	 */
	this.initRemoveParent = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		//enable hide/unhide functionality
		container.find("[role~='removeParent']").map(function(e) {
			$(this).on('click', function (e) {
				e.preventDefault();
				self.removeParent(this);
				return true;
			});
		});
	}
	
	/**
	 * Remove the parent element up to a certain depth
	 */
	this.initCloneParent = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		//enable hide/unhide functionality
		container.find("[role~='cloneParent']").map(function(e) {
			$(this).on('click', function (e) {
				e.preventDefault();
				self.cloneParent(this);
				if($(this).data('propagate') == undefined)
					$(this).click();
				return true;
			});
		});
	}
	
	/**
	 * Remove the parent element up to a certain depth
	 */
	this.removeParent = function (elem)
	{
		var $element = $(elem);
		var levels = ($element.data('depth') == undefined) ? -1 : $element.data('depth');
		switch($element.data('parent') != undefined)
		{
			case true:
			switch($element.data('parent') != undefined)
			{
				case true:
				var parent = $element.parents($element.data('parent')).eq(levels);
				break;
				
				default:
				var parent = $element.parents($element.data('parent'));
				break;
			}
			break;
			
			default:
			var parent = $element.parents().eq(levels);
			break;
		}
		parent.hide('slow').remove();
	}
	
	/**
	 * Remove the parent element up to a certain depth
	 */
	this.cloneParent = function (elem)
	{
		var $element = $(elem);
		var clone = $nitm.getObj($element.data('clone')).clone();
		clone.find('input').not(':hidden').each(function (){$(this).val('')});
		var currentId = !clone.attr('id') ? clone.prop('tagName') : clone.attr('id');
		clone.attr('id', currentId+Date.now());
		var to = $nitm.getObj($element.data('to'));
		if($element.data('after') != undefined) {
			clone.insertAfter(to.find($element.data('after')));
		}
		else if($element.data('before') != undefined)  {
			clone.insertBefore(to.find($element.data('before')));
		} else {
			to.append(clone);
		}
		eval("var afterClone = "+$element.data('after-clone'));
		if(typeof afterClone == 'function'){
			afterClone(clone, to, elem);
		}
		return clone;
	}
	
	/**
	 * Initialize remove parent elements
	 */
	this.initDisableParent = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		//enable hide/unhide functionality
		container.find("[role~='disableParent']").map(function(e) {
			$(this).on('click', function (e) {
				e.preventDefault();
				self.disableParent(this);
			});
		});
	}
	
	
	/**
	 * Disable the parent element up to a certain depth
	 */
	this.disableParent = function (elem, levels, parentOptions, disablerOptions, dontDisableFields) {
		var $element = $(elem);
		switch($element.data('parent') != undefined)
		{
			case true:
			var parent = $nitm.getObj($element.data('parent'));
			break;
			
			default:
			var levels = ($element.data('depth') == undefined) ? ((levels == undefined) ? 1 : levels): $element.data('depth');
			var parent = $element.parent();
			for(i = 0; i<levels; i++)
			{
				parent = parent.parent();
			}
			break;
		}
		//If we're dealing with a form, start from the submit button
		switch($element.prop('tagName'))
		{
			case 'FORM':
			var elem = $element.find(':submit').get(0);
			break;
		}
		
		/*
		 * For some reason this cdoe block doesn't make sense...
		 */
		$element.attr('role', 'disableParent');
		//get and set the role of the element activating this removal process
		var thisRole = $(this).attr('role');
		$(this).attr('role', (thisRole == undefined) ? 'disableParent' : thisRole);
		var thisRole = $(this).attr('role');
		
		//get and set the disabled data attribute
		switch($element.data('disabled'))
		{
			case 1:
			case true:
			var disabled = 1;
			break;
				
			default:
			var disabled = ($element.data('disabled') == undefined) ? 1 : 0;
			break;
		}
		$element.data('disabled', !disabled);
		
		var _defaultDisablerOptions = {
			size: !$element.attr('class') ? 'btn-sm' : $element.attr('class'),
			indicator: ((disabled == 1) ? 'refresh' : 'ban'),
			class: $element.attr('class')
		};
		//change the button to determine the curent status
		var _disablerOptions = {};
		for(var attribute in _defaultDisablerOptions)
		{
			try {
				_disablerOptions[attribute] = (disablerOptions.hasOwnProperty(attribute)) ? disablerOptions[attribute] : _defaultDisablerOptions[attribute];
			} catch(error) {
				_disablerOptions[attribute] = _defaultDisablerOptions[attribute];
			}
			
		};
		$element.removeClass().addClass(_disablerOptions.class+' '+_disablerOptions.size).html("<span class='fa fa-"+_disablerOptions.indicator+"'></span>");
		
		//now perform disabling on parent
		var _defaultParentOptions = {
			class: ((disabled == 1) ? 'bg-disabled' : 'bg-success')
		};
		var elemEvents = ['click'];
		parent.find(':input,:button,a').map(function () {
			switch($(this).attr('role'))
			{
				case thisRole:
				break;
					
				default:
				switch($(this).data('keep-enabled') || ($(this).attr('name') == '_csrf'))
				{
					case false:
					var _class = 'warning';
					var _icon = 'plus';
					if(disabled) {
						var _class = 'danger';
						var _icon = 'ban';
					}
					if(!dontDisableFields)
					{
						for(var event in elemEvents)
						{
							var func = disabled ? function (event) {return false;} : function (event) {$(this).trigger(event);};
							$(this).on(event, func);
						}
						if(disabled)
							$(this).attr('disabled', disabled);
						else
							$(this).removeAttr('disabled');
								break;
					}
				}
				break;
			}
		});
		
		var _parentOptions = {};
		for(var attribute in _defaultParentOptions)
		{
			try {
				_parentOptions[attribute] = (parentOptions.hasOwnProperty(attribute)) ? parentOptions[attribute] : _defaultParentOptions[attribute];
			} catch(error) {
				_parentOptions[attribute] = _defaultParentOptions[attribute];
			}
			
		}
		parent.removeClass().addClass(_parentOptions.class);
	}
	
	/**
	 * Fix for loading multiple boostrap modals
	 */
	this.initBsMultipleModal = function () {
		//to support multiple modals
		$(document).on('hidden.bs.modal', function (e) {
			$(e.target).removeData('bs.modal');
			//Fix a bug in modal which doesn't properly reload remote content
			$(e.target).find('.modal-content').html('');
		});
	}
	
	/**
	 * Custom auto complete handler
	 */
	this.initAutocompleteSelect = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		container.find("[role~='autocompleteSelect']").each(function() {
			$(this).on('autocompleteselect', function (e, ui) {
				e.preventDefault();
				var element = $(this).data('real-input');
				var appendTo = $(this).data('append-html');
				switch(appendTo != undefined)
				{
					case true:
					switch(ui.item.html != undefined)
					{
						case true:
						$nitm.getObj(appendTo).append($(ui.item.html));
						break;
					}
					break;
				}
				switch(element != undefined)
				{
					case true:
					$nitm.getObj(element).val(ui.item.value);
					$(this).val(ui.item.text);
					break;
						
					default:
					$(this).val(ui.item.value);
					break;
				}
			});
		});
	}
	
	/**
	 * Off canvas menu support
	 */
	this.initOffCanvasMenu = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		$(document).ready(function () {
			$("[data-toggle='offcanvas']").click(function () {
				$('.row-offcanvas').toggleClass('active')
			});
		});
	}
	
	/**
	 * Off tooltip support
	 */
	this.initToolTips = function () {
		$(document).ready(function() {
			$("body").tooltip({ selector: '[data-toggle=tooltip]' });
		});
	}
}

$nitm.initModule(new Tools());