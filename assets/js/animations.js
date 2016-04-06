'use strict';
/*!
 * Nitm v1 (http://www.ninjasitm.com)
 * Copyright 2012-2014 NITM, Inc.
 */
class Animations
{
	constructor() {
		this.id = 'animations';
		this.defaultInit = [
			'initEvents'
		];
	}

	initEvents() {
		/**
		 * Animation hanlders
		 */
		$nitm.on('start-spinner', (event, element, message) => {
			this.startSpinner(element, message);
		});

		$nitm.on('stop-spinner', (event, element) => {
			this.stopSpinner(element);
		});

		$nitm.on('aimate-indicate', (event, message, elem, className) => {
			this.indicate(message, elem, className);
		});

		$nitm.on('animate-submit-start', (event, form) => {
			this.animateSubmit(form);
		});

		$nitm.on('animate-submit-stop', (event, form) => {
			this.animateSubmit(form, true);
		});

		$nitm.on('scroll-to', (event, position, $object) => {
			this.animatScroll(position, $object);
		});

		//$nitm.module('entity').initSearch();
		$(document).on('pjax:send', (xhr, options) => {
			this.startAnimateAjax(xhr.target);
			//$(xhr.target).fadeOut('slow');
		});
		$(document).on('pjax:complete', (xhr, options) => {
			this.stopAnimateAjax(xhr.target);
		});
	}

	startAnimateAjax(target) {
		$(target).html('<div class="ajax-spinner"></div><style>.ajax-spinner { position: absolute; left: 0; right: 0; top: 0; bottom: 0; margin: auto; height: 3em; width: 3em; animation: rotate 0.8s infinite linear; border: 8px solid #000; border-right-color: transparent; border-radius: 50%;} @keyframes rotate { 0% { transform: rotate(0deg); } 100%  { transform: rotate(360deg); } }</style>');
		$nitm.trigger('activity', [target.id]);
	}

	stopAnimateAjax(target) {
		$(target).fadeIn('slow');
		$nitm.trigger('activity', [target.id]);
	}

	animateScroll (elem, parent, highlight)
	{
		let $element = $($nitm.getObj(elem).get(0));
		let $container = $nitm.getObj(((!parent) ? $element.parent().attr('id') : parent));
		switch(true)
		{
			case ($element.position().top > $container.height()) && ($element.position().top < 0):
			scrollToPos = $container.scrollTop + $element.position().top;
			break;

			default:
			scrollToPos = $element.position().top;
			break;
		}
		$container.animate({scrollTop: scrollToPos}, 150, function () {
			try
			{
				switch(highlight)
				{
					case true:
					$element.effect("pulsate", {times: 3}, 150, 'ease');
					break;
				}
			} catch(error) {}
		});
	};

	animateSubmit (form, after)
	{
		let $form = $nitm.getObj(form);

		if(!($form.get(0) instanceof HTMLElement))
			return;

		if($form.data('animation') !== undefined && !$form.data('animation'))
			return;

		let $button = [];
		let $found = {};
		if(($found.images = $form.find("[type='image']")).length >= 1)
			$button = $.merge($button, $found.images);

		if(($found.submits = $form.find("[type='submit']")).length >= 1)
			$button = $.merge($button, $found.submits);

		if(($found.globalSubmits = $('body').find("[type='submit'][form='"+$form.attr('id')+"']")).length >= 1)
			$button = $.merge($button, $found.globalSubmits);

		if(($found.animationTargets = $nitm.getObj($form.data('animation-target'))).length >= 1)
			$button = $.merge($button, $found.animationTargets);

		if($button.length === 0 && form.tagName != 'FORM')
			$button = $form;

		switch(after)
		{
			case true:
			this.stopSpinner($button);
			$nitm.trigger('activity', [form.id]);
			break;

			default:
			$nitm.trigger('activity', [form.id]);
			this.startSpinner($button, 'Saving...');
			break;
		}
	};

	startSpinner (elements, message) {
		if(elements.constructor !== 'Array')
			elements = [elements];
		console.info("[Nitm: Animations]: Starting spinner on "+elements);
		$.each(elements, function (key, elem) {
			let $element = $nitm.getObj(elem);
			try {
				if(!$element.data('has-spinner')) {
					$nitm.trigger('activity', [elem.id]);
					let style = $element.css(['font-size', 'line-height', 'width']);
					$element.data('oldContents', $element.html())
						.html('')
						.html("<span class='spinner'><i class='fa fa-spin fa-spinner'></i></span> "+(message || ''))
						.addClass('has-spinner active disabled')
						.attr('disabled', true);
					$element.data('has-spinner', true);
				}
			} catch (e) {
				console.warn(e);
			}
		});
	};

	stopSpinner (elements) {
		if(elements.constructor !== 'Array')
			elements = [elements];
		console.info("[Nitm: Animations]: Stopping spinner on "+elements);
		$.each(elements, function (key, elem) {
			try {
				let $element = $nitm.getObj(elem);
				if($element.data('has-spinner')) {
					if(!$element.data('animation-start-only')) {
						let oldContents = $element.data('oldContents');
						$element.html('').append(oldContents);
					}
					$element.removeClass('has-spinner active disabled')
						.removeData('oldContents')
						.removeAttr('disabled');
					$element.removeData('has-spinner');
					$nitm.trigger('activity', [elem.id]);
				}
			} catch (e) {
				console.warn(e);
			}
		});
	};

	indicate (message, elem, className) {
		let $elem = $nitm.getObj(elem);
		try {
			$elem.tooltip('destroy');
			$elem.tooltip({
				html: true,
				title: "<h3>"+message+"</h3>"
			});
			$elem.tooltip('show');
			if(className !== undefined)
				$elem.addClass(className);
		} catch (error) {}
	};
}

$nitm.addOnLoadEvent(function () {
	$nitm.initModule(new Animations());
});
