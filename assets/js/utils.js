'use strict';
/*!
 * Nitm v1 (http://www.ninjasitm.com)
 * Copyright 2012-2014 NITM, Inc.
 */
class Utils
{
	constructor() {
		this.id = 'utils';
		this.responseSection = 'alert';
		this.classes = {
			warning: 'alert alert-warning',
			success: 'alert alert-success',
			info: 'alert alert-info',
			error: 'alert alert-danger',
			danger: 'alert alert-danger',
			hidden: 'hidden',
		};

		this.defaultInit = [
			'initEvents'
		];
	}

	initEvents() {
		/**
		 * Util handlers
		 */
		$nitm.on('notify', (event, message, type, object) => {
			this.notify(message, type, object);
		});

		$nitm.on('activity', (event, elem) => {
		   this.updateActivity(elem);
		});

		$nitm.on('place', (event, newElem, data, addToElem, format, clear) => {
			this.place(newElem, data, addToElem, format, clear);
		});

		$nitm.on('toggle-inputs', (event, form, activating) => {
			this.toggleInputs(form, activating);
		});
	}

	toggleInputs(form, activating) {
		let $form = $(form);
		if(activating)
			$form.removeAttr('disabled');
		else
			$form.attr('disabled', true);
		$form.find(':input').each((key, elem) => {
			let $elem = $(elem);
			if(activating === true) {
				if($elem.data('wasDisabled')) {
					$elem.removeAttr('disabled').removeClass('disabled').removeData('wasDisabled');
				}
			} else {
				if(($elem.attr('disabled') === undefined) && (!$elem.hasClass('disabled'))) {
					$elem.attr('disabled', true).addClass('disabled').data('wasDisabled', true);
				}
			}
		});
	};

	/* gap is in millisecs */
	delay(gap) {
		let then = new Date().getTime();
		let now = then;
		while((now-then) < gap)
		{
			now = new Date().getTime();
			//notify(now, 'notify', true);
		}
	};

	popUp (url, id, h, w, scr)
	{
		let day = new Date();
		id = id || day.getTime();
		h = (eval(h) !== undefined) ? h : '800';
		w = (eval(w) !== undefined) ? w : '720';
		scr = ((eval(scr)) === 0) ? 'no' : 'yes';
		window.open(url, id, 'toolbar=0,scrollbars='+scr+',location=no,statusbar=no,menubar=no,resizable=no,width='+w+',height='+h);
		return false;
	};

	dialog (message, options) {
		let body = $("<div class='modal fade in' role='dialog' aria-hidden='true' style='z-index:100000'>");
		options = options === undefined ? {} : options;
		let title = options.title === undefined ? '<h3>Message</h3>' : options.title;
		let actions = options.actions === undefined ? '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>' : options.actions;
		let dialogClass = options.dialogClass === undefined ? 'default' : options.dialogClass;

		$.each(['title', 'actions', 'dialogClass'], function (property) {
			delete options[property];
		});

		try {
			let modalDialog = $("<div class='modal-dialog'>");
			let modalContent = $("<div class='modal-content "+dialogClass+"'>");
			let modalBody = $("<div class='modal-body'>");
			let modalTitle = $("<div class='modal-title'>").append(title);
			let modalHeader = $("<div class='modal-header'>");
			let modalFooter= $("<div class='modal-footer'>");
			let modalClose = $('<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>');
			modalHeader.append(modalClose);
			modalHeader.append(modalTitle);
			modalContent.append(modalHeader);
			modalBody.append(message);
			modalContent.append(modalFooter);
			modalFooter.append(actions);
			body.append(modalDialog.append(modalContent.append(modalBody)));
			body.modal($.extend(options, {
				show: true
			}));
		} catch(e) {
			body = $('<div class="dialog" style="z-index: 100000">').html(message);
			body.dialog($.extend(options, {
				resizable: false,
				modal: true,
				show: 'clip'
			}));
		}
	};

	notify (message, type, object) {
		try {
			$.notify({
				message: message,
			}, {
				position: 'fixed',
				z_index: 10000,
				type: type,
				placement: {
					from: "top",
					align: "right"
				},
				animate: {
					exit: 'animated fadeOutUp',
					enter: 'animated fadeInDown'
				},
			});
		} catch (e) {
			self.notifyInternal(message, type, object);
		}
	};

	notifyInternal (newMessage, newClass, newObject) {
		let obj = null;
		switch(true)
		{
			case newObject instanceof HTMLElement:
			obj = $(newObject).siblings('#alert');
			obj = obj.length <= 0 ? $(newObject).parents().find('#alert').last() : obj;
			break;

			case newObject instanceof Array:
			case newObject instanceof Object:
			obj = $(newObject[0]).parents().find(newObject[1]).last();
			break;

			case newObject instanceof jQuery:
			obj = newObject;
			break;

			case typeof newObject == 'string':
			obj = $nitm.getObj(newObject);
			break;

			default:
			obj = $nitm.getObj(this.responseSection);
			break;
		}
		if(obj instanceof jQuery)
		{
			let id = 'alert'+Date.now();
			newClass = this.classes.hasOwnProperty(newClass) ? this.classes[newClass] : newClass;
			let message = $('<div id="'+id+'" class="'+newClass+'">').html(newMessage.toString());
			obj.append(message).fadeIn();
			setTimeout(function () {$('#'+id).fadeOut();$('#'+id).remove();}, 10000);
		}
		return obj;
	};

	clearNotify ()
	{
		$nitm.getObj(this.responseSection).html("");
	};

	//function fo focus items with special box
	setFocus (item)
	{
		let obj = $nitm.getObj(item);
		obj.effect('pulsate', {times:2}, 'fast');
		obj.focus();
	};

	toggleElem (selector, by, by_val)
	{
		return new Promise(function (resolve, reject) {
			if(['number', 'string', 'object'].indexOf(typeof selector) === -1) {
				resolve();
				return;
			}
			switch(typeof selector)
			{
				case 'object':
				selector = (selector.id === undefined) ? selector.name : selector.id;
				break;
			}
			selector = $nitm.jqEscape(selector);
			switch(by)
			{
				case 'name':
				selector = selector+' [name="'+by_val+'"]';
				break;

				case 'class':
				let obj = (selector[0] != '.') ? '.'+selector : selector;
				obj = '\\'+obj;
				break;

				default:
				selector = (selector[0] != '#') ? '#'+selector : selector;
				break;
			}
			try {
				$nitm.getObj(selector, '', false, false).each(function() {
					this.disabled = !this.disabled;
				});
				resolve();
			} catch(error) {
				console.warn('[Nitm: Utils]: toggleElem() Error: '+error);
				resolve();
			}
		});
	};

	handleVis (e, onlyShow)
	{
		return new Promise(function (resolve, reject) {
			if(onlyShow)
				$nitm.getObj(e).each(function () {
					resolve();
					if($(this).hasClass('hidden') && $(this).is(':hidden'))
						$(this).css('display', 'none').removeClass('hidden');
					$(this).show('slow');
				});
			else
				$nitm.getObj(e).each(function () {
					resolve();
					if($(this).hasClass('hidden') && $(this).is(':hidden'))
						$(this).css('display', 'none').removeClass('hidden');
					$(this).slideToggle('slow');
				});
		});
	};

	visibility (id, pour, caller)
	{
		return new Promise(function (resolve, reject) {
			let data = {
				getHtml: false,
				for: pour,
				unique: id
			};
			let requestData = {0:"visibility", 1:data};
			let request = $nitm.doRequest({'module':'api', 'proc':'procedure', 'data':requestData});
			request.done(function(result) {
				if(result) {
					let newAction = (result.data.hidden === 0) ? 'hide' : 'show';
					$(caller).text(newAction);
					if(Number(result.data.hidden) === 0)
						$(caller).parents("div[id='note_content"+id+"']").removeClass('hidden_displayed');
					else
						$(caller).parents("div[id='note_content"+id+"']").addClass('hidden_displayed');
				}
				resolve();
			}).catch(function (xhr, status, error) {
				console.warn('[Nitm: Utils]: visibility() Error: '+error);
				resolve();
			});
		});
	};

	place (newElem, data, addToElem, format, clear)
	{
		let promise = new Promise(function (resolve, reject) {
			let $newElement = null;
			if(typeof newElem === 'object') {
				let $addTo = $nitm.getObj(addToElem);
				let scrollToPos = 0;

				if(format === 'text') {
					$newElement = $('<div style="width:100%; padding:10px;" id="text_result"><br>'+data+'</div>');
					scrollToPos = $newElement.get(0).id;
				} else {
					$newElement = $(data);
					scrollToPos = $newElement.get(0).id;
				}

				switch(typeof clear)
				{
					case 'string':
					$addTo.find(clear).html('');
					break;

					case 'boolean':
					if(clear === true) {$addTo.html('');}
					break;
				}
				if(newElem.prepend === true) {
					try {
						switch($addTo.find(':first-child').attr('id'))
						{
							case 'noreplies':
							$addTo.find(':first-child').remove();
							break;
						}
						$newElement.appendTo($addTo);
						$newElement.hide();
						$nitm.m('nitm:animations').animateScroll(scrollToPos, $addTo);
						resolve([scrollToPos, $addTo]);
					} catch(error){
						reject();
					}
				} else if(newElem.replace === true) {
					try {
						$addTo.replaceWith(data).effect('pulsate', {times:1}, 150);
						$nitm.m('nitm:animations').animateScroll(scrollToPos, $addTo);
						resolve([scrollToPos, $addTo]);
					}catch(error){
						reject();
					}
				} else {
					try {
						if(!$addTo.children().length) {
							$addTo.append($newElement).next().hide();
						} else {
							switch($addTo.find(':first-child').attr('id'))
							{
								case 'noreplies':
								$addTo.find(':first-child').hide();
								$newElement.prependTo('#'+$addTo).hide();
								break;

								default:
								if(newElem.index === -1) {
									$newElement.prependTo($addTo).hide();
								} else {
									$addTo.children().eq(newElem.index)
									.after($newElement).next().hide();
								}
								break;
							}
						}
						$nitm.m('nitm:animations').animateScroll(scrollToPos, $addTo);
						resolve([scrollToPos, $addTo]);
					} catch(error){
						reject();
					}
				}
			}
		});
		promise.then(function () {
			if($newElement !== undefined)
				$newElement.slideDown('fast');
		})
		return promise;
	};

	updateActivity (id) {
		if(!id)
			return false;
		if(this.hasActivity(id)) {
			$nitm.getObj(id).removeData('nitmActivity');
		} else {
			$nitm.getObj(id).data('nitmActivity', true);
		}
	};

	hasActivity (id) {
		return (id !== undefined) ? ( $nitm.getObj(id).data('nitmActivity') === true) : false;
	};

	hasNoActivity(id) {
		return new Promise((resolve, reject) => {
			if($nitm.getObj(id).data('nitmActivity') !== true)
				return resolve(true);
			else {
				return setTimeout(() => {
					return this.hasNoActivity(id);
				}, 500);
			}
		});
	}

	activityId (id) {
		let $elem = $nitm.getObj(id);
		return $elem.prop('tagName')+'-'+id;
	};
}

$nitm.addOnLoadEvent(function () {
	$nitm.initModule(new Utils());
});
