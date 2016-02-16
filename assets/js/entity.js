'use strict';
// JavaScript Document

class NitmEntity {
	constructor(id) {
		this.id = id || 'entity';
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
			approveAction: 'approveAction',
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
		this.globalEvents = {
			'.modal': {
				events: ['loaded.bs.modal'],
				callback: function (event) {
					console.log(event);
				}
			}
		};
		this.defaultInit = [
			'initEvents'
		];

		this.errorCount = 0;
	}

	init(container, key, defaults) {
		this.initDefaults(container, key, defaults);
	};

	initDefaults(container, key, userDefaults) {
		$nitm.initDefaults(key || this.id, this, userDefaults || this.defaultInit, container || this.views.containerId);
	};

	initModule(object, name) {
		name = name === undefined ? object.id : name;
		try {
			/**
			 * Init the defaulfs for the object
			 */
			['views'].map((property, i) => {
				try {
					$.extend(true, object[property], this[property]);
				} catch(error) {
					object[property] = this[property];
				}
			});
			$nitm.initModule(object, name, object.defaultInit, this.id);
		} catch (error) {console.log(error);}
	};

	initAjaxEvents(logName, events) {
		logName = logName || 'Entity';
		events = events || 'pjax:success loaded.bs.modal';
		//Perform certain actions after pjax success
		$(document).on(events, (data, status, xhr, options) => {
			let moduleId = $(data.relatedTarget).data('module') || this.id;
			console.info("["+logName+"]: Running helper scripts after event ("+data.type+"."+data.namespace+")  on module: "+moduleId+" and HTML element: #"+data.target.id);
			this.initDefaults('#'+data.target.id, moduleId, this.defaultInit);
			$(document).trigger('nitm:entity-ajax-event', [data.target.id]);
		});
	}

	getContainer(containerId) {
		if(containerId !== undefined)
			return containerId;
		else if(this.views.hasOwnProperty('containerId'))
			return this.views.containerId;
		else
			return 'body';
	};

	initMetaActions(containerId) {
		let $container = $nitm.getObj(this.getContainer(containerId));
		console.info("[Nitm: Entity]: Initing metaActions for "+this.id+": roles are ["+this.actions.roles+"]");
		$.map(this.actions.roles, (v, i) => {
			$container.find("[role~='"+v+"']").map((i, elem) => {
				let $elem = $(elem);
				if(!$elem.data('nitm-entity-click')) {
					$elem.data('nitm-entity-click', true);
					$elem.on('click', (e) => {
						e.preventDefault();
						let $target = $(e.currentTarget);
						let proceed = true;

						if($target.attr('role').includes(this.actions.deleteAction))
							proceed = confirm("Are you sure you want to delete this?");

						if(proceed === true) {
							$nitm.trigger('start-spinner', [e.currentTarget]);
							let successFunc = $target.data('success-callback') === undefined ?  (result) => {
								$nitm.trigger('stop-spinner', [$target]);
								this.afterAction(result.action || $target.data('action'), result, e.currentTarget);
								if($target.data('after-callback')) {
									let afterCallback = $target.data('after-callback').parseFunction();
									if(afterCallback && typeof afterCallback == 'function')
										(function (elem) {afterCallback.call(elem);})(e.currentTarget);
								}
							} : $target.data('success-callback').parseFunction();

							let errorFunc = $target.data('error-callback') === undefined ? (xhr, text, error) => {
								$nitm.trigger('stop-spinner', [e.currentTarget]);
								let message = "An error occurred while reading the data!: <br><br><i> "+(xhr.responseText || text)+"</i>";
								if($nitm.debug === true)
									message += "<br><br>Detailed error is: <br><br><i>"+error+"</i>";

								$nitm.trigger('notify', [message, 'danger']);
							} : $target.data('error-callback').parseFunction();

							let url = $target.data('url') || $target.attr('href');
							if(url[0] != '#') {
								$.ajax({
									method: $target.data('method') || 'post',
									url: url,
									success: successFunc,
									error: errorFunc,
									dataType: $target.data('type') ||  'json',
								});
							} else {
								$nitm.trigger('stop-spinner', [e.currentTarget]);
							}
						}
						return false;
					});
				}
			});
		});
	};

	updateActivity(id) {
		$nitm.m('utils').updateActivity(id);
	};

	hasActivity(id) {
		return $nitm.m('utils').hasActivity(id);
	};

	hasNoActivity(id) {
		return $nitm.m('utils').hasNoActivity(id);
	};

	initForms(containerId) {
		let $container = $nitm.getObj(this.getContainer(containerId));
		console.info("[Nitm: Entity]: Initing forms for "+this.id);
		$.map(this.forms.roles, (role, key) => {
			$container.find("form[role~='"+role+"']").map((i, elem) => {
				let $elem = $(elem);
				if(!$elem.data('nitm-entity-form-submit')) {
					$elem.data('nitm-entity-form-submit', true);
					$elem.data('module');
					$elem.on('submit', (e) => {
						e.preventDefault();
						let form = e.target,
							$form = $(form);

						$nitm.trigger('animate-submit-start', [form]);

						if($form.data('yiiActiveForm')) {
							if($form.data('yiiActiveForm').validated === false) {
								$nitm.trigger('animate-submit-stop', [form]);
								return false;
							} else {
								this.operation(form, null, e);
							}
						} else {
							this.operation(form, null, e);
						}
					});
				}
			});
		});
	};

	afterAction(action, result, elem, realElem) {
		let func = 'after'+$nitm.safeFunctionName(action || 'none');
		console.info("[Nitm: Entity]: Running afterAction:"+action+" for "+this.id+". Searching for : "+func);
		if(typeof this[func] == 'function') {
			this[func](result, realElem || elem);
		}
		let indicate = null;
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
			$nitm.trigger('notify', [result.message, indicate, (!realElem ? elem : realElem)]);
		}
	};

	operation(form, callback, event) {

		$nitm.trigger('animate-submit-start', [form]);
		let request = Promise.resolve();
		try {
			try {
				form = event.target || form;
				event.preventDefault();
			} catch (error) {}

			let data = [],
				$form = null;
			if(form.tagName == 'FORM') {
				//Try serializing the form data
				$form = $(form);
				data = $form.serializeArray();
			} else {
				//Otherwise the form is the data. So create a form and assign the data
				data = $nitm.objectToSerializedArray(form.data);
				$form = $(document.createElement('form'));
				$form.attr('action', form.action || '/');
				$form.attr('method', form.type || 'get');
				$form.attr('id', 'form'+$.now())
				form = $form.get(0);
				$('body').append(form);
				console.log(form);
			}
			data.push({'name':'__format', 'value':'json'});
			data.push({'name':'getHtml', 'value':true});
			data.push({'name':'do', 'value':true});
			data.push({'name':'ajax', 'value':true});

			$nitm.trigger('toggle-inputs', [form]);

			if($form.attr('action')) {
				let request = $nitm.doRequest({
					url: $form.attr('action'),
					data: data,
					success: (result) => {
						if(typeof callback == 'function')
							callback(result, form, this);
						else {
							let originalEventTarget;
							//if the module already has a method for this action
							try {
								originalEventTarget = event.originalEvent.explicitOriginalTarget;
							} catch (error) {}
							this.afterAction(result.action || 'none', result, form, originalEventTarget);
						}
					},
					error: (xhs, status, error) => {
						$nitm.trigger('animate-submit-stop', [form]);
						$nitm.trigger('toggle-inputs', [form, true]);
						this.errorCount++;
						if(this.errorCount < 3) {
							$nitm.trigger('notify', ['Whoops something went wrong. Try again. If it keeps happening let the lazy  admin know!', 'warning', form]);
						} else
							$nitm.trigger('nidm:dialog', ['This won\'t work anymore. Please notify the admin. The error is: <br><br><code>'+error+'</code>', {
								dialogClass: 'error'
							}]);
					},
					type: $form.attr('method'),
				});
				request.done((result) => {
					$nitm.trigger('animate-submit-stop', [form]);
					$nitm.trigger('toggle-inputs', [form, true]);
					this.errorCount = 0;
				});
			}
		} catch (e) {
			$nitm.trigger('animate-submit-stop', [form]);
			$nitm.trigger('toggle-inputs', [form, true]);
		}
		return request;
	};

	afterNone(result, form, containerId) {
		if(result.success === true)
		{
			try{
				form.reset();
			} catch (e) {}
			let message = result.message || "Success!";
			if(result.data) {
				try {
					$nitm.getObj(this.views.containerId).find('.empty').hide();
				} catch (e) {}
				$nitm.trigger('place', [{append:false, index:0}, result.data, this.views.containerId]);
				this.initMetaActions(this.getIds(this.views.itemId, result.id));
			}
		}
		else
		{
			$nitm.trigger('notify', [result.message || "An error occurred", 'error', form]);
		}
	};

	afterCreate(result, form, containerId) {
		if(result.success === true) {
			try{
				form.reset();
			} catch (e) {}
			let message = !result.message ? "Success! You can add another or view the newly added one" : result.message;
			if(result.data)
			{
				$nitm.getObj(containerId || this.views.containerId).find('.empty').hide();
				$nitm.trigger('place', [{append:false, index:0}, result.data, containerId || this.views.containerId]);
				this.initMetaActions(this.getIds(this.views.itemId, result.id));
			}
		}
		else
		{
			$nitm.trigger('notify', [(!result.message ? "Couldn't create item" : result.message), 'error', form]);
		}
	};

	afterUpdate(result, form, containerId) {
		if(result.success)
		{
			let message = !result.message ? "Update successful!" : result.message;
			if(result.data)
			{
				//Remove any items that are related with role~=itemExtra
				$("[role~='"+this.views.itemExtra+result.id+"']").map((i, elem) => {
					$(elem).remove();
				});
				this.initMetaActions(this.getIds(this.views.itemId, result.id));
			}
		}
		else
		{
			$nitm.trigger('notify', [(!result.message ? "Couldn't update item" : result.message), 'error', form]);
		}
	};

	afterClose(result, elem, containerId) {
		return this.afterDisable (result, elem, containerId);
	};

	afterDisable(result, elem, containerId) {
		if(result.success)
		{
			this.getItem(elem, result.id).each((index, element) => {
				let $container = $(element);
				$container.find("[role~='"+this.actions.disabledOnClose+"']").map((i, elem) => {
					let $elem = $(elem);
					if($elem.css('visbility') === undefined) {
						let visibility = ($elem.css('visbility') == 'hidden') ? 'visible' : 'hidden';
						$elem.css('visbility', visibility);
					} else {
						$elem.toggleClass('hidden', result.data);
					}
				});
				$container.find("[role~='"+this.views.replyForm+"']").toggleClass(this.classes.hidden, result.data);
				$(elem).attr('title', result.title).find(':first-child').replaceWith(result.actionHtml);

				element = $("[role~='"+this.views.statusIndicator+result.id+"']");
				element.removeClass().addClass(result.class);
			});
		}
	};

	afterDelete(result, elem, containerId) {
		if(result.success)
		{
			try {
				$nitm.module('tools').removeParent(elem);
			} catch (error) {
				let $container = this.getItem(elem, result.id);
				if($container.length >= 1)
					$container.remove();
			}
		}
	};

	afterApprove(result, elem, containerId) {
		return this.afterResolve(result, elem, containerId);
	};

	afterComplete(result, elem, containerId) {
		return this.afterResolve(result, elem, containerId);
	};

	afterResolve(result, elem, containerId) {
		if(result.success)
		{
			this.getItem(elem, result.id).each((index, element) => {
				let $container = $(element);
				$container.find("[role~='"+this.actions.disabledOnResolve+"']").toggleClass(this.classes.hidden, result.data);
				element = $("[role~='"+this.views.statusIndicator+result.id+"']");
				element.removeClass().addClass(result.class);
				$(elem).attr('title', result.title).html(result.actionHtml);
			});
		}
	};

	afterDuplicate(result, elem, containerId) {
		if(result.success)
		{
			this.getItem(elem, result.id).each((index, element) => {
				let $container = $(element);
				$container.removeClass().addClass(result.class);
				$(elem).attr('title', result.title).find(':first-child').replaceWith(result.actionHtml);
			});
		}
	};

	getItem(elem, id) {
		let baseName, $elem = $(elem);
		try {
			baseName = this.views.itemId;
		} catch (error) {
			baseName = null;
		}
		let parent = ($elem.data('parent') !== undefined) ? $elem.data('parent') : '.item';
		if(!baseName)
			return $(elem).parents(parent).first();
		else
			return $nitm.getObj(this.getIds(baseName, id));
	};

	getIds(from, ids) {
		switch(typeof from) {
			case 'string':
			case 'number':
			from = (typeof from == "number") ? from.toString() : from;
			from = from.includes(',') ? from.split : new Array(from);
			break;
		}
		switch(typeof ids) {
			case 'string':
			case 'number':
			ids = (typeof ids == "number") ? ids.toString() : ids;
			ids = ids.includes(',') ? ids.split(',') : new Array(ids);
			break;
		}
		if(typeof ids == 'object') {
			for (let i=0; i < from.length; i++) {
				if(ids.hasOwnProperty(i))
					from[i] += ids[i];
			}
		}
		try {
			return '#'+from.join(', #');
		} catch (e) {}
	};
}

$nitm.addOnLoadEvent(function () {
	$nitm.initModule(new NitmEntity());
});
