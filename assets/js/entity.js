
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
	};

	this.initDefaults = function (container, key, userDefaults) {
		var defaults = userDefaults !== undefined ? userDefaults: self.defaultInit;
		defaults.map(function (method) {
			try {
				self[method](container || self.views.containerId, key || self.id);
			} catch (error){}
		});
	};

	this.initModule = function (object, name) {
		name = name === undefined ? object.id : name;
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
		} catch (error) {console.log(error);}
	};

	this.getContainer = function (containerId) {
		if(containerId !== undefined)
			return containerId;
		else if(this.views.hasOwnProperty('containerId'))
			return self.views.containerId;
		else
			return 'body';
	};

	this.initMetaActions = function (containerId, currentIndex) {
		var container = $nitm.getObj(this.getContainer(containerId));
		$.map(self.actions.roles, function (v) {
			container.find("[role~='"+v+"']").map(function() {
				if(!$(this).data('nitm-entity-click')) {
					$(this).data('nitm-entity-click', true);
					$(this).on('click', function (e) {
						var $elem = $(this);
						e.preventDefault();
						var proceed = true;

						if($elem.attr('role').indexOf(self.actions.deleteAction) != -1)
							proceed = confirm("Are you sure you want to delete this?");

						if(proceed === true)
						{
							$nitm.startSpinner($elem);
							var successFunc = $elem.data('success-callback') === undefined ? function (result) {
								$nitm.stopSpinner($elem);
								self.afterAction(result.action || $(this).data('action'), result, currentIndex, $elem.get(0));
								if($elem.data('after-callback')) {
									var afterCallback = $elem.data('after-callback').parseFunction();
									if(afterCallback && typeof afterCallback == 'function')
										(function (elem) {afterCallback.call(elem);})($elem.get(0));
								}
							} : $elem.data('success-callback').parseFunction();

							var errorFunc = $elem.data('error-callback') === undefined ? function (xhr, text, error) {
								$nitm.stopSpinner($elem);
								var message = "An error occurred while reading the data!: <br><br><i> "+(xhr.responseText || text)+"</i>";
								if($nitm.debug === true)
									message += "<br><br>Detailed error is: <br><br><i>"+error+"</i>";

								$nitm.notify(message, 'danger');
							} : $elem.data('error-callback').parseFunction();

							var url = $elem.data('url') || $elem.attr('href');
							if(url[0] != '#') {
								$.ajax({
									method: $elem.data('method') || 'post',
									url: url,
									success: successFunc,
									error: errorFunc,
									dataType: $elem.data('type') ||  'json',
								});
							} else {
								$nitm.stopSpinner($elem);
							}
						}
						return false;
					});
				}
			});
		});
	};

	this.updateActivity = function (id) {
		$nitm.updateActivity(id);
	};

	this.hasActivity = function (id) {
		return $nitm.hasActivity(id);
	};

	this.initForms = function (containerId, currentIndex) {
		var container = $nitm.getObj(this.getContainer(containerId)), roles;
		try {
			roles = $nitm.module(currentIndex).forms.roles;
		} catch(error) {
			roles = self.forms.roles;
		}
		$.map(roles, function(role, key) {
			console.info("Initing forms: "+role+" for "+currentIndex);
			container.find("form[role~='"+role+"']").map(function() {
				if(!$(this).data('nitm-entity-form-submit')) {
					$(this).data('nitm-entity-form-submit', true);
					var $form = $(this);
					$form.data('module', currentIndex);
					$form.on('submit', function (e) {
						e.preventDefault();
						if($form.data('yiiActiveForm') !== undefined) {
							if($form.data('yiiActiveForm').validated === true)
								self.operation($form.get(0), null, $form.data('module'), e);
						}
						else
							self.operation($form.get(0), null, $form.data('module'), e);
					});
				}
			});
		});
	};

	this.afterAction = function (action, result, currentIndex, elem, realElem) {
		console.log("Running afterAction:"+action+" for "+currentIndex);
		var func = 'after'+$nitm.safeFunctionName(action || 'none');
		try {
			$nitm.module(currentIndex)[func](result, currentIndex, elem);
		} catch(error) {
			if(typeof self[func] == 'function') {
				self[func](result, currentIndex, elem);
			}
		}
		var indicate;
		if(result.message) {
			switch(result.action)
			{
				case 'update':
				case 'create':
				indicate = result.indicate || 'info';
				break;

				default:
				indicate = result.indicate || 'notify';
				break;
			}
			$nitm.notify(result.message, indicate, (!realElem ? elem : realElem));
		}
	};

	this.operation = function (form, callback, currentIndex, event) {
		if(self.hasActivity(form.id))
			return false;

		self.updateActivity(form.id);

		self.setCurrent(currentIndex);
		try {
			event.preventDefault();
		} catch (error) {}

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
						var originalEventTarget;
						//if the module already has a method for this action
						try {
							originalEventTarget = event.originalEvent.explicitOriginalTarget;
						} catch (error) {
						}
						self.afterAction(result.action || 'none', result, currentIndex, form, originalEventTarget);
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
	};

	this.toggleInputs = function (form, activating) {
		$(form).find(':input').each(function (key, elem) {
			var $elem = $(elem);
			if(activating === true) {
				if($elem.data('wasDisabled')) {
					$elem.removeAttr('disabled').removeClass('disabled').data('was-disabled', false);
				}
			}
			else {
				if(($elem.attr('disabled') === undefined) && (!$elem.hasClass('disabled'))) {
					$elem.attr('disabled', true).addClass('disabled').data('wasDisabled', true);
				}
			}
		});
	};

	this.afterNone = function (result, currentIndex, form) {
		self.setCurrent(currentIndex);
		if(result.success === true)
		{
			form.reset();
			var message = result.message || "Success!";
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
			$nitm.notify(result.message || "An error occurred", $nitm.classes.error, form);
		}
	};

	this.afterCreate = function (result, currentIndex, form) {
		self.setCurrent(currentIndex);
		if(result.success === true)
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
	};

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
	};

	this.afterClose= function (result, currentIndex, elem) {
		return this.afterDisable (result, currentIndex, elem);
	};

	this.afterDisable = function (result, currentIndex, elem) {
		self.setCurrent(currentIndex);
		if(result.success)
		{
			self.getItem(elem, result.id).each(function(index, element) {
				var container = $(element);
				container.find("[role~='"+self.actions.disabledOnClose+"']").map(function () {
					if($(this).css('visbility') === undefined) {
						var visibility = ($(this).css('visbility') == 'hidden') ? 'visible' : 'hidden';
						$(this).css('visbility', visibility);
					} else {
						$(this).toggleClass($nitm.classes.hidden, result.data);
					}
				});
				container.find("[role~='"+self.views.replyForm+"']").toggleClass($nitm.hidden, result.data);
				var actionElem = container.find("[role~='"+self.actions[result.action+'Action']+"']");
				actionElem.attr('title', result.title).find(':first-child').replaceWith(result.actionHtml);

				element = $("[role~='"+self.views.statusIndicator+result.id+"']");
				element.removeClass().addClass(result.class);
			});
		}
	};

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
	};

	this.afterComplete = function (result, currentIndex, elem) {
		return this.afterResolve(result, currentIndex, elem);
	};

	this.afterResolve = function (result, currentIndex, elem) {
		self.setCurrent(currentIndex);
		if(result.success)
		{
			self.getItem(elem, result.id).each(function(index, element) {
				var container = $(element);
				container.find("[role~='"+self.actions.disabledOnResolve+"']").toggleClass($nitm.hidden, result.data);
				element = $("[role~='"+self.views.statusIndicator+result.id+"']");
				element.removeClass().addClass(result.class);

				var actionElem = container.find("[role~='"+self.actions[result.action+'Action']+"']");
				actionElem.attr('title', result.title).html(result.actionHtml);
			});
		}
	};

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
	};

	this.getItem = function (elem, id) {
		var baseName, $module = $nitm.module(self.current), $elem = $(elem);
		try {
			baseName = $module.views.itemId;
		} catch (error) {
			baseName = null;
		}
		var parent = ($elem.data('parent') !== undefined) ? $elem.data('parent') : '.item';
		if(!baseName)
			return $(elem).parents(parent).first();
		else
			return $nitm.getObj(self.getIds(baseName, id));
	};

	this.getIds = function (from, ids) {
		switch(typeof from) {
			case 'string':
			case 'number':
			from = (typeof from == "number") ? from.toString() : from;
			from = (from.indexOf(',') != -1) ? from.split : new Array(from);
			break;
		}
		switch(typeof ids) {
			case 'string':
			case 'number':
			ids = (typeof ids == "number") ? ids.toString() : ids;
			ids = (ids.indexOf(',') != -1) ? ids.split(',') : new Array(ids);
			break;
		}
		if(typeof ids == 'object') {
			for (var i=0; i < from.length; i++) {
				if(ids.hasOwnProperty(i))
					from[i] += ids[i];
			}
		}
		return '#'+from.join(', #');
	};

	this.setCurrent = function (currentIndex) {
		try {
			currentIndex = (typeof currentIndex != 'string') ? this.id : currentIndex;
			self.current = (currentIndex === undefined) ? self.current : currentIndex.split(':').pop();
		} catch(error) {
			console.log(error);
		}
	};
}

$nitm.addOnLoadEvent(function () {
	$nitm.initModule(new NitmEntity());
});
