// JavaScript Document

function NitmEntity () {
	var self = this;
	this.id = 'entity';
	this._activity = {};
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
		itemExtra: 'itemExtra'
	};
	this.modules = {};
	this.defaultInit = [
	];
		
	this.initDefaults = function (container, key) {
		$nitm.initDefaults((key == undefined ? this.id : key), undefined, undefined, container);
	}
		
	this.initModule = function (object, name) {
		try {
			self.setCurrent($nitm.getModuleName(object, name));
			/**
			 * Init the defaulfs for the object
			 */
			$nitm.initModule(object, name, object.defaultInit);	
		} catch (error) {console.log(error);};
	}
	
	this.initMetaActions = function (containerId, currentIndex) {
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		$.map(self.actions.roles, function (v) {
			container.find("[role~='"+v+"']").map(function() {
				//$(this).off('click');
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
						var method = $elem.data('method') == 'get' ? 'get' : 'post';
						$[method]($elem.attr('href'), function (result) { 
							$nitm.stopSpinner($elem);
							try {
								var func = 'after'+$nitm.safeFunctionName(result.action);
								self[func](result, currentIndex, $elem.get(0));
							} catch (error) {};
						}, 'json');
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
				if(self.hasActivity($(e.target).attr('id')))
					return false;
				self.updateActivity($(e.target).attr('id'));
				e.preventDefault();
				var request = self.operation(_form, function(result, form, xmlHttp) {
					var replaceId = $(form).data('id');
					$nitm.notify(result.message, $nitm.classes.info, form);
					$nitm.getObj(replaceId).replaceWith(result.data);
					//history.pushState({}, result.message, xmlHttp.url);
					self.updateActivity($(e.target).attr('id'));
				});
			}
			$(this).find(':input').on('change', function (e) {submitFunction(e)});
			$(this).on('submit', function (e) {submitFunction(e)});
		});
	}
	
	this.updateActivity = function (id) {
		if(id == undefined)
			return;
		if(this.hasActivity(id))
			delete self._activity[this.activityId(id)];
		else {
			self._activity[this.activityId(id)] = true;
		}
	}
	
	this.hasActivity = function (id) {
		return (id != undefined) ? self._activity.hasOwnProperty(this.activityId(id)) : false;
	}
	
	this.activityId = function (id) {
		var $elem = $nitm.getObj(id);
		return $elem.prop('tagName')+'-'+id;
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
				$form.off('submit');
				$form.on('submit', function (e) {
					e.preventDefault();
					if(self.hasActivity(this.id))
						return false;
					self.updateActivity(this.id);
					return self.operation(this, null, currentIndex, e);
				});
			});
		});
	}
	
	this.operation = function (form, callback, currentIndex, event) {
		self.setCurrent(currentIndex);
		try {
			event.preventDefault();
		} catch (error) {};
		
		var $form = $(form);
		var proceed = false;
		try {
			var $data = $form.data('yiiActiveForm');
			proceed = true;
			if($data.attributes.length >= 1)
				if(($data.submitting || !$data.validated) && !$form.data('validated'))
					$form.one('ajaxComplete.yiiActiveForm', function (ajaxEvent, xhr, settings) {
						self.operation(form, callback, currentIndex, event);
					});
				else
					proceed = $data.validated;
			else
				proceed = true;
		} catch (error) {
			proceed = true;
		}
		
		if(!proceed)
			return;
		
		$form.data('validated', true);
		data = $form.serializeArray();
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
					switch(typeof callback == 'function')
					{
						case true:
						callback(result, form, this);
						break;
						
						default:
						//if the module already has a method for this action
						var func = 'after'+$nitm.safeFunctionName(result.action);
						try {
							$nitm.module(currentIndex)[func](result, currentIndex, form);
						} catch(error) {
							if(typeof self[func] == 'function') {
							self[func](result, form, currentIndex);
							} else {
								try {
									self[func](result, currentIndex, elem);
								} catch (error) {};
							}
						}
						break;
					}
				},
				error: function () {
					$nitm.notify('Whoops something went wrong. Try again. If it keeps happening let the lazy  admin know!', $nitm.classes.error, form);
				},
				type: $form.attr('method'),
			});
			request.done(function () {
				self.toggleInputs(form, true);
				self.updateActivity($form.attr('id'));
				$($nitm).trigger('nitm-animate-submit-stop', [form]);
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
			$nitm.notify(message, $nitm.classes.success, form);
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
			$nitm.notify(message, $nitm.classes.success, form);
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
					switch($(this).css('visbility') == undefined)
					{
						case false:
						var visibility = ($(this).css('visbility') == 'hidden') ? 'visible' : 'hidden';
						$(this).css('visbility', visibility);
						break;	
											
						default:
						$(this).toggleClass($nitm.classes.hidden, result.data);
						break;
					}
				});
				container.find("[role~='"+self.views.replyForm+"']").toggleClass($nitm.hidden, result.data);
				var actionElem = container.find("[role~='"+self.actions[result.action+'Action']+"']");
				actionElem.attr('title', result.title);
				actionElem.find(':first-child').replaceWith(result.actionHtml);
				var element = container.parent().find("[role~='"+self.views.statusIndicator+result.id+"']");
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
				container.parent().find("[role~='"+self.views.statusIndicator+result.id+"']").removeClass().addClass(result.class);
				container.find("[role~='"+self.actions.disabledOnResolve+"']").toggleClass($nitm.hidden, result.data);
				var actionElem = container.find("[role~='"+self.actions[result.action+'Action']+"']");
				actionElem.attr('title', result.title);
				actionElem.html(result.actionHtml);
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
				actionElem.attr('title', result.title);
				actionElem.find(':first-child').replaceWith(result.actionHtml);
			});
		}
	}
	
	this.getItem = function (elem, id) {
		var $module = $nitm.module(self.current);
		try {
			var baseName = $module.views.itemId;
		} catch (error) {
			var baseName = null;
		}
		if(!baseName)
			return $(elem).parents(".item").first();
		else
			return $nitm.getObj(self.getIds(baseName, id))
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