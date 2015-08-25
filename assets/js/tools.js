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
		'initConfirm'
	];
	this._activity = {};
	
	this.init = function (containerId) {
		this.initDefaults(containerId);
	}
	
	this.initDefaults = function (containerId) {
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
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);	
		container.find("[role~='changeSubmit']").map(function(e) {
			if(!$(this).data('nitm-entity-change')) {
				$(this).data('nitm-entity-change', true);
				$(this).off('change');
				$(this).on('change', function (event) {
					window.location.replace($(this).val());
				});
			}
		});
	}
	
	/**
	 * Use data attributes to load a URL into a container/element
	 */
	this.initVisibility = function (containerId) {
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);
		//enable hide/unhide functionality with optional data retrieval
		container.find("[role~='visibility']").map(function(e) {
			var $target = $(this);
			if($target.data('id') != undefined) {
				var events = $target.data('events') != undefined ? $target.data('events').split(',') : ['click'];
				$.each(events, function (index, eventName) {
					if(!$target.data('nitm-entity-'+eventName)) {
						$target.data('nitm-entity-'+eventName, true);
						if($target.data('no-animation'))
							var _callback = function (e) {
								e.preventDefault();
								self.visibility($target.get(0))
								return true;
							}
						else
							var _callback = function (e) {
								e.preventDefault();
								$.when(self.visibility($target.get(0))).done(function () {
								});
								return true;
							}
						if($target.data('run-once'))
							$target.one(eventName, _callback);
						else
							$target.on(eventName, _callback);
					}
				});
			}
		});
	}
	
	this.visibility = function (object, removeListener) {
		
		$($nitm).trigger('nitm-animate-submit-start', [object]);
		
		
		var _visSelf = this;
		var $object = $nitm.getObj(object);
		var on = $object.data('on');
		
		var getUrl = true;
		var url = !$object.data('url') ? $object.attr('href') : $object.data('url');
		
		if($(this).data('on') != undefined)
			if($(this).data('on').length == 0) getUrl = false;
		
		var getRemote = function () {
			var basedOnGetUrl = (url != undefined) && (url != '#') && (url.length >= 2) && getUrl;
			var basedOnRemoteOnce = ($object.data('remote-once') != undefined) ? (Boolean($object.data('remote-once')) && !$object.data('got-remote')) : true;
			return basedOnGetUrl && basedOnRemoteOnce;
		}
		
		this.target = $nitm.getObj($object.data('id'));
		
		if(getRemote())
		{
			this.success = ($object.data('success') != undefined) ? $object.data('success') : null;
			this.url = $object.data('url') ? $object.data('url') : $object.attr('href');
			var ret_val = $.ajax({
				url: url,
				type: ($object.data('method') != undefined) ? $object.data('method') : 'get',  
				dataType: $object.data('type') ? $object.data('type') : 'html',
				complete: function (result) {
					$nitm.module('tools').replaceContents(result.responseText, object, _visSelf);
				}
			});
			$object.data('got-remote', true);
		}
		
		$($nitm).trigger('nitm-animate-submit-stop', [object]);
		
		$nitm.handleVis($object.data('id'));
		
		if($object.data('toggle-inputs')) {
			this.target.find(':input').prop('disabled', function(i, v) { return !v; });
			console.log($(this).data());
		}
		
		return false;
	}
	
	this.replaceContents = function (result, object, visibility) {
		var $object = $nitm.getObj(object);
		if($object.data('toggle')) {
			$.when($nitm.handleVis($object.data('toggle'))).done(function () {
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
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);		
		container.find("[role~='dynamicDropdown']").map(function(e) {
			var id = $(this).data('id');
			var $target = $(this);
			switch(id != undefined)
			{
				case true:
				if(!$target.data('nitm-entity-change')) {
					$target.data('nitm-entity-change', true);
					$target.off('change');
					$target.on('change', function (e) {
						e.preventDefault();
						var element = $nitm.getObj('#'+id);
						var url = $target.data('url');
						if((url != '#') && (url.length >= 2)) {
							element.removeAttr('disabled');
							element.empty();	
							var ret_val = $.get(url+$target.find(':selected').val()).done( function (result) {
								var result = $.parseJSON(result);
								element.append( $('<option></option>').val('').html('Select value...') );
								if(typeof result == 'object')
								{
									$.each(result, function(val, text) {
										element.append( $('<option></option>').val(text.value).html(text.label) );
									});
								}
							}, 'json');
						}
						return true;
					});
				}
				break;
			}
		});
	}
	
	/**
	 * Set the value for an element using data attributes
	 */
	this.initDynamicValue = function (containerId) {
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);
		//enable hide/unhide functionality with optional data retrieval
		container.find("[role~='dynamicValue']").map(function(e) {
			var $target = $(this);
			switch(($target.data('id') != undefined) || ($target.data('type') != undefined))
			{
				case true:
				var events = $target.data('events') != undefined ? $target.data('events').split(',') : ['click'];
				$.each(events, function (index, eventName) {
					if(!$target.data('nitm-entity-'+eventName)) {
						$target.data('nitm-entity-'+eventName, true);
						var _callback = function (e) {
							e.preventDefault();
							$.when(self.dynamicValue($target.get(0))).done(function () {
							});
							return true;
						}
						if($target.data('run-once'))
							$target.one(eventName, _callback);
						else
							$target.on(eventName, _callback);
					}
				});
				break;
			}
		});
	}
	
	/**
	 * Set the value for an element using data attributes
	 */
	this.initDynamicIframe = function (containerId) {
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);
		//enable hide/unhide functionality with optional data retrieval
		container.find("[role~='dynamicIframe']").map(function(e) {
			if(($(this).data('id') != undefined))
				var _target = this;
				var events = $(this).data('events') != undefined ? $(this).data('events').split(',') : ['click'];
				$.each(events, function (index, eventName) {
					if(!$target.data('nitm-entity-'+eventName)) {
						$target.data('nitm-entity-'+eventName, true);
						if($target.data('run-once')) {
							$target.one(eventName, function (e) {
								e.preventDefault();
								$.when(self.dynamicIframe(_target)).done(function () {
								});
							});
						} else {
							$target.on(eventName, function (e) {
								e.preventDefault();
								$.when(self.dynamicIframe(_target)).done(function () {
								});
							});
						}
					}
				});
		});
	}
	
	this.dynamicIframe = function (object) {
		var $object = $nitm.getObj(object);
		var $target = $nitm.getObj($object.data('id'));
		if(($indicator = $nitm.getObj($object.data('indicator'))).get(0) == undefined)
			$indicator = $object;
		
		$indicator.text('Loading...').fadeIn();
		$target.fadeOut();
		var url = $nitm.getObj(object).attr('href');
		
		if($nitm.hasActivity($object.attr('id')))
			return;
		
		$($nitm).trigger('nitm-animate-submit-start', [$indicator.get(0)]);
			$target.attr('src', ($(object).data('url') ? $object.data('url') : $object.attr('href')));
			$target.load(function () {
				$(this).fadeIn();
				$indicator.fadeOut();
			});
		$($nitm).trigger('nitm-animate-submit-stop', [$indicator.get(0)]);
	}
	
	this.dynamicValue = function (object) {
		
		var $object = $nitm.getObj(object);
		var $target = $nitm.getObj($object.data('id'));
		
		if($nitm.hasActivity($object.attr('id')))
			return;
		
		$($nitm).trigger('nitm-animate-submit-start', [object]);
		
		$nitm.updateActivity($object.attr('id'));
		
		var ret_val = null;
		
		try {
			var element = !$target.get(0) ? $object : $target;
		} catch (e) {var element = $object;}
		
		if(element.data('run-once') && (element.data('run-times') >= 1))
			return;
		var url = !$object.data('url') ? $object.attr('href') : $object.data('url');
		var on = $object.data('on');
		switch((url != '#') && (url.length >= 2))
		{
			case true:
			element.removeAttr('disabled');
			element.empty();	
			var selected = !$object.find(':selected').val() ? '' : $object.find(':selected').val();
			if(on != undefined)
				if($(on).get(0) == undefined) return false;
			
			var ajaxSettings = {
				url: url+selected,
				method: (($object.data('method') != undefined) ? $object.data('method') : (($object.data('ajaxMethod') != undefined) ? $object.data('ajaxMethod') : 'get')), 
				error: function (xhr, status, error) {
					$nitm.indicate(error, object);
				}
			}
			switch($object.data('type'))
			{
				case 'html':
				$.extend(ajaxSettings, {
					dataType: 'html',
					success: function (result) {
						self.evalScripts(result, function (responseText) {
							element.html(responseText);
						});
					}
				});
				break;
				
				case 'callback':
				eval("var callback = "+$object.data('callback'));
				$.extend(ajaxSettings, {
					dataType: 'json',
					success: function (result) {
						callback(result, element.get(0));
					}
				});
				break;
				
				default:
				$.extend(ajaxSettings, { 
					dataType: 'text',
					success: function (result) {
						element.val(result);
					}
				});
				break;
			}
			var ret_val = $.ajax(ajaxSettings).done(function () {
				element.data('run-times', 1);
				$nitm.updateActivity($object.attr('id'));
			});
			break;
		}
		$($nitm).trigger('nitm-animate-submit-stop', [object]);
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
				if(options != undefined) {
					var existing = (!options.context) ? false : options.context.attr('id');
				} else {
					var existing = false;
				}
				var wrapperId = !existing ? false : $nitm.getObj(existing).find("[role='nitmToolsAjaxWrapper']").attr('id');
				if(wrapperId){
					var wrapper = $('#'+wrapperId);
					wrapper.html('').html(dom.html());
				} else {
					var wrapperId = 'nitm-tools-ajax-wrapper'+Date.now();
					var wrapper = $('<div id="'+wrapperId+'" role="nitmToolsAjaxWrapper">');
					wrapper.append(dom);
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
					self.initDefaults(wrapperId);
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
	 * Remove the parent element up to a certain depth
	 */
	this.initRemoveParent = function (containerId) {
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);
		//enable hide/unhide functionality
		container.find("[role~='removeParent']").map(function(e) {
			if(!$(this).data('nitm-entity-click')) {
				$(this).data('nitm-entity-click', true);
				$(this).on('click', function (e) {
					e.preventDefault();
					self.removeParent(this);
					return true;
				});
			}
		});
	}
	
	/**
	 * Remove the parent element up to a certain depth
	 */
	this.initCloneParent = function (containerId) {
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);
		//enable hide/unhide functionality
		container.find("[role~='cloneParent']").map(function(e) {
		if(!$(this).data('nitm-entity-click')) {
			$(this).data('nitm-entity-click', true);
				$(this).on('click', function (e) {
					e.preventDefault();
					self.cloneParent(this);
					if($(this).data('propagate') == undefined)
						$(this).click();
					return true;
				});
			}
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
			if($element.data('parent') != undefined)
				var parent = $element.parents($element.data('parent')).eq(levels);
			else
				var parent = $element.parents($element.data('parent'));
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
	this.cloneParent = function (elem, callbacks)
	{
		var $element = $(elem);
		var clone = $nitm.getObj($element.data('clone')).clone();
		clone.find('input').not(':hidden').each(function (){$(this).val('')});
		var currentId = !clone.attr('id') ? clone.prop('tagName') : clone.attr('id');
		clone.attr('id', currentId+Date.now());
		var to = $nitm.getObj($element.data('to'));
		
		if(typeof callbacks.before == 'function') {
			clone = callbacks.before(clone, to, elem);
		} else if($element.data('before-clone') != undefined)  {
			eval("var beforeClone = "+$element.data('before-clone'));
			clone = beforeClone(clone, to, elem);
		}
		
		if($element.data('after') != undefined) {
			clone.insertAfter(to.find($element.data('after')));
		}
		else if($element.data('before') != undefined)  {
			clone.insertBefore(to.find($element.data('before')));
		} else {
			to.append(clone);
		}
		
		if(typeof callbacks.after == 'function') {
			callbacks.after(clone, to, elem);
		} else if($element.data('after-clone') != undefined)  {
			eval("var afterClone = "+$element.data('after-clone'));
			afterClone(clone, to, elem);
		}
		return clone;
	}
	
	/**
	 * Initialize remove parent elements
	 */
	this.initDisableParent = function (containerId) {
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);
		//enable hide/unhide functionality
		container.find("[role~='disableParent']").map(function(e) {
			if(!$(this).data('nitm-entity-click')) {
				$(this).data('nitm-entity-click', true);
				$(this).on('click', function (e) {
					e.preventDefault();
					self.disableParent(this);
				});
			}
		});
	}
	
	
	/**
	 * Disable the parent element up to a certain depth
	 */
	this.disableParent = function (elem, levels, parentOptions, disablerOptions, dontDisableFields) {
		var $element = $(elem);
		if($element.data('parent') != undefined)
			var parent = $nitm.getObj($element.data('parent'));
		else {
			var levels = ($element.data('depth') == undefined) ? ((levels == undefined) ? 1 : levels): $element.data('depth');
			var parent = $element.parent();
			for(i = 0; i<levels; i++)
			{
				parent = parent.parent();
			}
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
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);
		container.find("[role~='autocompleteSelect']").each(function() {
			$(this).on('autocompleteselect', function (e, ui) {
				e.preventDefault();
				var element = $(this).data('real-input');
				var appendTo = $(this).data('append-html');
				if(appendTo != undefined)
					if(ui.item.html != undefined)
						$nitm.getObj(appendTo).append($(ui.item.html));
						
				if(element != undefined) {
					$nitm.getObj(element).val(ui.item.value);
					$(this).val(ui.item.text);
				} else {
					$(this).val(ui.item.value);
				}
			});
		});
	}
	
	/**
	 * Off canvas menu support
	 */
	this.initOffCanvasMenu = function (containerId) {
		var container = $nitm.getObj((!containerId) ? 'body' : containerId);
		$(document).ready(function () {
			$("[data-toggle='offcanvas']").click(function () {
				$('.row-offcanvas').toggleClass('active')
			});
		});
	}
	
	this.initConfirm = function () {
		$(document).ready(function () {
			$('[data-confirm]').on('click', function (event) {
				if(!confirm($(this).data('confirm'))) {
					event.preventDefault();
					event.stopImmediatePropagation();
				} else {
					return true;
				}
			}).each(function () {
				var listeners = $._data(this, "events").click;
				listeners.reverse();
			});
		});
	}
	
	/**
	 * Off tooltip support
	 */
	this.initToolTips = function () {
		try {
			$(document).ready(function() {
				$("body").tooltip({ selector: '[data-toggle=tooltip]' });
			});
		} catch (error) {}
	}
}

$nitm.addOnLoadEvent(function () {
	$nitm.initModule(new Tools());
	//$nitm.module('entity').initSearch();
	$(document).on('pjax:send', function (xhr, options) {
		$(xhr.target).fadeOut('slow');
	});
	$(document).on('pjax:complete', function (xhr, options) {
		$(xhr.target).fadeIn('slow');
	});
		
	$(document).on('pjax:beforeReplace', function (content, options) {
		$nitm.module('tools').evalScripts(content, function (text) {return true;});
	});
});