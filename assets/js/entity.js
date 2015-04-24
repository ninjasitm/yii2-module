
// JavaScript Document

function NitmEntity () {
	var self = this;
	this.id = 'entity';
	this.selfInit = false;
	this.current = '';
	this.classes = {
		warning: 'bg-warning',
		success: 'bg-success',
		information: 'bg-info',
		error: 'bg-danger',
		hidden: 'hidden',
	};
	this.forms = {
		roles: {
			ajaxSearch: "filter",
			ajaxForm: 'ajaxForm',
		}
	};
	
	this.buttons = {
		roles: ['ajaxForm']
	};
	this.actions = {
		roles: ['metaAction'],
		updateAction: 'updateAction',
		disabledOnClose: 'disabledOnClose',
		disabledOnResolve: 'disabledOnResolve',
		resolveAction: 'resolveAction',
		closeAction: 'closeAction',
		deleteAction: 'deleteAction',
		disableAction: 'disableAction',
		duplicateAction: 'duplicateAction',
		hasSpinner: 'spinnerAction',
	};
	this.views = {
		statusIndicator: 'statusIndicator',
		itemExtra: 'itemExtra',
	};
	this.modules = {};
	this.defaultInit = [
	];
	
	this.errorCount = 0;
	
	this.init = function (container, key, defaults) {
		this.initDefaults(container, key, defaults);
	}
	
	this.initDefaults = function (container, key, defaults) {
		var defaults = defaults !== undefined ? defaults: self.defaultInit;
		defaults.map(function (method) {
			try {
				var containerId = (container == undefined) ? self.views.containerId : container;
				self[method](container, key);
			} catch (error){}
		});
	}
		
	this.initModule = function (object, name) {
		try {
			self.setCurrent($nitm.getModuleName(object, name));
			/**
			 * Init the defaulfs for the object
			 */
			['views'].map(function (property) {
				try {
					$.extend(true, object[property], self[property]);
				} catch(error) {
					object[property] = self[property];
				}
			});
			$nitm.initModule(object, name, object.defaultInit);
		} catch (error) {console.log(error);};
	}
	
	this.initMetaActions = function (containerId, currentIndex) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		$.map(self.actions.roles, function (v) {
			container.find("[role~='"+v+"']").map(function() {
				$(this).off('click');
				$(this).on('click', function (e) {
					var $elem = $(this);
					e.preventDefault();
					var proceed = true;
					if($elem.attr('role').indexOf(self.actions.deleteAction) != -1)
						if(!confirm("Are you sure you want to delete this?"))
							proceed = false;
					
					if(proceed === true)
					{
						$nitm.startSpinner($elem);
						var successFunc = $elem.data('data-success-callback') == undefined ? function (result) {
							$nitm.stopSpinner($elem);
							self.afterAction(result.action, result, currentIndex, $elem.get(0));
						} : $elem.data('data-success-callback');
						
						var errorFunc = $elem.data('data-error-callback') == undefined ? function (xhr, text, error) {
							$nitm.stopSpinner($elem);
							var message = "An error occurred while reading the data!\nThe error was:<i> "+text+"</i>";
							if($nitm.debug == true)
								message += "<br><br>Detailed error is: <br><br><i>"+error+"</i>";
								
							$nitm.notify(message, 'danger');
						} : $elem.data('data-error-callback');
						
						if($elem.attr('href') || $elem.data('url')) {
							var url = !$elem.attr('href') ? $elem.attr('url') : $elem.attr('href');
							$.ajax({
								method: $elem.data('method') == 'get' ? 'get' : 'post',
								url: url, 
								success: successFunc, 
								error: errorFunc,
								dataType: $elem.data('data-type') != undefined ? $elem.data('data-type') : 'json',
							});
						}
					}
				});
			});
		});
	}
	
	this.initSearch = function (containerId) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		$nitm.getObj(container).find("form[role~='"+this.forms.roles.ajaxSearch+"']").map(function() {
			var _form = this;
			$(this).off('submit');
			var submitFunction = function (e) {
				e.preventDefault();
				$(_form).data('yiiActiveForm').validated = true;
				var request = self.operation(_form, function(result, form, xmlHttp) {
					var replaceId = $(form).data('id');
					$nitm.notify(result.message, $nitm.classes.info, form);
					$nitm.getObj(replaceId).replaceWith(result.data);
					//history.pushState({}, result.message, xmlHttp.url);
				});
			}
			$(this).find(':input').on('change', function (e) {submitFunction(e)});
			$(this).on('submit', function (e) {submitFunction(e)});
		});
	}
	
	this.updateActivity = function (id) {
		$nitm.updateActivity(id);
	}
	
	this.hasActivity = function (id) {
		return $nitm.hasActivity(id);
	}
	
	this.initForms = function (containerId, currentIndex) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		try {
			var roles = $nitm.module(currentIndex).forms.roles;
		} catch(error) {
			var roles = self.forms.roles;
		}
		$.map(roles, function(role, key) {
			container.find("form[role~='"+role+"']").map(function() {
				var $form = $(this);
				$form.on('submit', function (e) {
					e.preventDefault();
					if($form.data('yiiActiveForm') != undefined) {
						$form.one('beforeSubmit', function (event) {
							if($form.data('yiiActiveForm').validated)
								self.operation($form.get(0), null, currentIndex, e);
						});
					}
					else {
						self.operation($form.get(0), null, currentIndex, e);
					}
				});
			});
		});
	}
	
	this.afterAction = function (action, result, currentIndex, elem, realElem) {
		var func = 'after'+$nitm.safeFunctionName(action);
		try {
			$nitm.module(currentIndex)[func](result, currentIndex, elem);
		} catch(error) {
			if(typeof self[func] == 'function') {
				self[func](result, currentIndex, elem);
			}
		}
		if(result.message) {
			switch(result.action)
			{
				case 'update':
				case 'create':
				var indicate = result.indicate || 'info';
				break;
				
				default:
				var indicate = 'notify';
				break;
			}
			$nitm.notify(result.message, indicate, (realElem == undefined ? elem : realElem));
		}
	}
	
	this.operation = function (form, callback, currentIndex, event) {
		
		if(self.hasActivity(form.id))
			return false;
			
		self.updateActivity(form.id);
		
		self.setCurrent(currentIndex);
		try {
			event.preventDefault();
		} catch (error) {};
		
		var $form = $(form);
		
		var data = $form.serializeArray();
		data.push({'name':'__format', 'value':'json'});
		data.push({'name':'getHtml', 'value':true});
		data.push({'name':'do', 'value':true});
		data.push({'name':'ajax', 'value':true});
		switch(!$(form).attr('action'))
		{
			case false:
			self.toggleInputs(form);
			$($nitm).trigger('nitm-animate-submit-start', [form]);
			var request = $nitm.doRequest({
				url: $(form).attr('action'), 
				data: data,
				success: function (result){
					if(typeof callback == 'function')
						callback(result, form, this);
					else {
						//if the module already has a method for this action
						try {
							var originalEventTarget = event.originalEvent.explicitOriginalTarget;
						} catch (error) {
							var originalEventTarget = undefined;
						}
					}
				},
				error: function (xhs, status, error) {
					self.errorCount++;
					if(self.errorCount < 3) {
						$nitm.notify('Whoops something went wrong. Try again. If it keeps happening let the lazy  admin know!', $nitm.classes.warning, form);
						self.updateActivity($form.attr('id'));
					} else
						$nitm.dialog('This won\'t work anymore. Please notify the admin. The error is: <br><br><code>'+error+'</code>', {
							dialogClass: $nitm.classes.error
						});
					self.toggleInputs(form, true);
					$($nitm).trigger('nitm-animate-submit-stop', [form]);
				},
				type: $form.attr('method'),
			});
			request.done(function () {
				self.toggleInputs(form, true);
				self.updateActivity($form.attr('id'));
				$($nitm).trigger('nitm-animate-submit-stop', [form]);
				self.errorCount = 0;
			});
			break;
		}
		$form.data('validated', false);
		return request;
	}
	
	this.toggleInputs = function (form, activating) {
		$(form).find(':input').each(function (key, elem) {
			var $elem = $(elem);
			if(activating === true) {
				if($elem.data('wasDisabled')) {
					$elem.removeAttr('disabled').removeClass('disabled').data('was-disabled', false);
				}
			}
			else {
				if(($elem.attr('disabled') == undefined) && (!$elem.hasClass('disabled'))) {
					$elem.attr('disabled', true).addClass('disabled').data('wasDisabled', true);
				}
			}
		});
	}
	
	this.afterCreate = function (result, currentIndex, form) {
		self.setCurrent(currentIndex);
		if(result.success == true)
		{
			form.reset();
			var message = !result.message ? "Success! You can add another or view the newly added one" : result.message;
			if(result.data)
			{
				var $module = $nitm.module(currentIndex);
				$nitm.getObj($nitm.module(currentIndex).views.containerId).find('.empty').hide();
				$nitm.place({append:false, index:0}, result.data, $nitm.module(currentIndex).views.containerId);
				self.initMetaActions(self.getIds($module.views.itemId, result.id));
			}
		}
		else
		{
			$nitm.notify((!result.message ? "Couldn't create item" : result.message), $nitm.classes.error, form);
		}
	}
	
	this.afterUpdate = function (result, form, currentIndex) {
		self.setCurrent(currentIndex);
		if(result.success)
		{
			var message = !result.message ? "Update successful!" : result.message;
			if(result.data)
			{
				//Remove any items that are related with role~=itemExtra
				$("[role~='"+self.views.itemExtra+result.id+"']").map(function () {
					$(this).remove();
				});
				var $module = $nitm.module(currentIndex);
				//$nitm.getObj(self.getIds($module.views.itemId, result.id)).replaceWith(result.data);
				self.initMetaActions(self.getIds($module.views.itemId, result.id));
			}
		}
		else
		{
			$nitm.notify((!result.message ? "Couldn't update item" : result.message), $nitm.classes.error, form);
		}
	}
	
	this.afterClose= function (result, currentIndex, elem) {
		return this.afterDisable (result, currentIndex, elem);
	}
	
	this.afterDisable = function (result, currentIndex, elem) {
		if(result.success)
		{
			self.getItem(elem, result.id).each(function(index, element) {
				var container = $(element);
				container.find("[role~='"+self.actions.disabledOnClose+"']").map(function () {
					if($(this).css('visbility') == undefined) {
						var visibility = ($(this).css('visbility') == 'hidden') ? 'visible' : 'hidden';
						$(this).css('visbility', visibility);
					} else {
						$(this).toggleClass($nitm.classes.hidden, result.data);
					}
				});
				container.find("[role~='"+self.views.replyForm+"']").toggleClass($nitm.hidden, result.data);
				var actionElem = container.find("[role~='"+self.actions[result.action+'Action']+"']");
				actionElem.attr('title', result.title).find(':first-child').replaceWith(result.actionHtml);
				
				var element = $("[role~='"+self.views.statusIndicator+result.id+"']");
				element.removeClass().addClass(result.class);
			});
		}
	}
	
	this.afterDelete = function (result, currentIndex, elem) {
		self.setCurrent(currentIndex);
		if(result.success)
		{
			try {
				$nitm.module('tools').removeParent(elem);
			} catch (error) {
				var container = self.getItem(elem, result.id);
				if(conatiner.length >= 1)
					container.remove();
			}
		}
	}	
	
	this.afterComplete = function (result, currentIndex, elem) {
		return this.afterResolve(result, currentIndex, elem);
	}
	
	this.afterResolve = function (result, currentIndex, elem) {
		self.setCurrent(currentIndex);
		if(result.success)
		{
			self.getItem(elem, result.id).each(function(index, element) {
				var container = $(element);
				container.find("[role~='"+self.actions.disabledOnResolve+"']").toggleClass($nitm.hidden, result.data);
				var element = $("[role~='"+self.views.statusIndicator+result.id+"']");
				element.removeClass().addClass(result.class);
					
				var actionElem = container.find("[role~='"+self.actions[result.action+'Action']+"']");
				actionElem.attr('title', result.title).html(result.actionHtml);
			});
		}
	}
	
	this.afterDuplicate = function (result, currentIndex, elem) {
		self.setCurrent(currentIndex);
		if(result.success)
		{
			self.getItem(elem, result.id).each(function(index, element) {
				var container = $(element);
				container.removeClass().addClass(result.class);
				var actionElem = container.find("[role~='"+self.actions.duplicateAction+"']");
				actionElem.attr('title', result.title).find(':first-child').replaceWith(result.actionHtml);
			});
		}
	}
	
	this.getItem = function (elem, id) {
		var $module = $nitm.module(self.current);
		var $elem = $(elem);
		try {
			var baseName = $module.views.itemId;
		} catch (error) {
			var baseName = null;
		}
		var parent = ($elem.data('parent') != undefined) ? $elem.data('parent') : '.item';
		if(!baseName)
			return $(elem).parents(parent).first();
		else
			return $nitm.getObj(self.getIds(baseName, id));
	}
	
	this.getIds = function (from, ids) {
		switch(typeof from) {
			case 'string':
			case 'number':
			var from = (typeof from == "number") ? (new Number(from)).toString() : from;
			var from = (from.indexOf(',') != -1) ? from.split : new Array(from);
			break;
		}
		switch(typeof ids) {
			case 'string':
			case 'number':
			var ids = (typeof ids == "number") ? (new Number(ids)).toString() : ids;
			var ids = (ids.indexOf(',') != -1) ? ids.split(',') : new Array(ids);
			break;
		}
		if(typeof ids == 'object') {
			for (var i=0; i < from.length; i++) {
				if(ids.hasOwnProperty(i))
					from[i] += ids[i];
			}
		}
		return '#'+from.join(', #');
	}
	
	this.setCurrent = function (currentIndex) {
		try {
			var currentIndex = (typeof currentIndex != 'string') ? this.id : currentIndex;
			self.current = (currentIndex == undefined) ? self.current : currentIndex.split(':').pop();
		} catch(error) {
			console.log(error);
		}
	}
}

$nitm.addOnLoadEvent(function () {
	$nitm.initModule(new NitmEntity());
	//$nitm.module('entity').initSearch();
	$(document).on('pjax:send', function (xhr, options) {
		$(xhr.target).fadeOut('slow');
	});
	$(document).on('pjax:complete', function (xhr, options) {
		$(xhr.target).fadeIn('slow');
	});
});