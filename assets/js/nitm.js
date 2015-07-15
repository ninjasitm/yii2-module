
/*!
 * Nitm v1 (http://www.ninjasitm.com)
 * Copyright 2012-2014 NITM, Inc.
 */

if (typeof jQuery === 'undefined') { throw new Error('Nitm\'s JavaScript requires jQuery') }

//var r = {'url':'/r/', 'type':'POST', 'dataType':'json', 'token':'c1e48dd56b43196a06a66b67ec3bede6'};
function Nitm ()
{
	var self = this;
	this.events = {};
	this.current ='';
	this.modules = {};
	this.r = {'url':'/r/', 'type':'POST', 'dataType':'json'};
	this.responseSection = 'alert';
	this.classes = {
		warning: 'alert alert-warning',
		success: 'alert alert-success',
		info: 'alert alert-info',
		error: 'alert alert-danger',
		danger: 'alert alert-danger',
		hidden: 'hidden',
	};
	
	/* gap is in millisecs */
	this.delay = function(gap) { 
		var then,now; 
		then=new Date().getTime();
		now=then;
		while((now-then) < gap)
		{
			now=new Date().getTime();
			//notify(now, 'notify', true);
		}
	}
	
	this.popUp = function (url, id, h, w, scr) 
	{
		day = new Date();
		id = day.getTime();
		h = (eval(h) != undefined) ? h : '800';
		w = (eval(w) != undefined) ? w : '720';
		scr = ((eval(scr)) == '0') ? 'no' : 'yes';
		window.open(url, id, 'toolbar=0,scrollbars='+scr+',location=no,statusbar=no,menubar=no,resizable=no,width='+w+',height='+h);
		return false;
	}	
	
	this.animateScroll = function (elem, parent, highlight)
	{
		var element = $(this.getObj(elem).get(0));
		var container = this.getObj(((!parent) ? element.parent().attr('id') : parent));
		switch(true)
		{
			case (element.position().top > container.height()) && (element.position().top < 0):
				var scrollToPos = container.scrollTop + element.position().top;
				break;
				
			default:
				var scrollToPos = element.position().top;
				break;
		}
		container.animate({scrollTop: scrollToPos}, 150, function () {
			try
			{
				switch(highlight)
				{
					case true:
					element.effect("pulsate", {times: 3}, 150, 'ease');
					break;
				}
			} catch(error) {};
		});
	}
	
	this.animateSubmit = function (form, after)
	{
		var $form = $nitm.getObj(form);
		switch(true)
		{
			case $form.data('animation') != undefined && !$form.data('animation'):
			return;
			break;
		}
		var $button = [];
		var $found = {};
		if(($found['images'] = $form.find("[type='image']")).length >= 1)
			var $button = $.merge($button, $found['images']);
		
		if(($found['submits'] = $form.find("[type='submit']")).length >= 1)
			var $button = $.merge($button, $found['submits']);
		
		if(($found['globalSubmits'] = $('body').find("[type='submit'][form='"+$form.attr('id')+"']")).length >= 1)
			var $button = $.merge($button, $found['globalSubmits']);
		
		if(($found['animationTargets'] = this.getObj($form.data('animation-target'))).length >= 1)
			var $button = $.merge($button, $found['animationTargets']);
		
		if($button.length == 0)
			var $button = $form;
		
		switch(after)
		{
			case true:
			this.stopSpinner($button);
			break
				
			default:
			this.startSpinner($button);
			break;
		}
	}
	
	this.startSpinner = function (elements) {
		$.each(elements, function (elem, key) {
			var element = self.getObj(this);
			var style = $(element).css(['font-size', 'line-height', 'width']);
			element.data('old-contents', element.html());
			element.html('');
			element.append("<span class='spinner'><i class='fa fa-spin fa-spinner'></i></span>");
			element.addClass('has-spinner active');
			element.attr('disabled', true);
		});
	}
	
	this.stopSpinner = function (elements) {
		$.each(elements, function (elem, key) {
			var element = self.getObj(this);
			if(!element.data('animation-start-only'))
				element.html(element.data('old-contents'));
			element.removeClass('has-spinner active');
			element.data('old-contents', '');
			element.removeAttr('disabled');
		});
	}
	
	this.getEvents = function(elem, type) {
		var events = $._data(elem, "events");
		var ret_val = {};
		for(var i in events[type])
		{
			if(events[type][i].handler)
			{
				ret_val[i] = events[type][i].handler;
			}
		}
		return ret_val;
	}
	
	this.setEvents = function(elem, type, events) {
		$(elem).off(type);
		try {
			for(var i in events)
			{
				$(elem).on(type, events[i]);
			}
		} catch (error) {}
	}
	
	this.indicate = function (message, elem, className) {
		var $elem = $nitm.getObj(elem);
		try {
			$elem.tooltip('destroy');
			$elem.tooltip({
				html: true,
				title: "<h3>"+message+"</h3>"
			});
			$elem.tooltip('show');
			if(className != undefined)
				$elem.addClass(className);
		} catch (error) {}
	}
	
	this.dialog = function (message, options) {
		
		var options = options == undefined ? {} : options;
		var title = options.title == undefined ? '<h3>Message</h3>' : options.title;
		var actions = options.actions == undefined ? '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>' : options.actions;
		var dialogClass = options.dialogClass == undefined ? 'default' : options.dialogClass;
		
		$.each(['title', 'actions', 'dialogClass'], function (property) {
			delete options[property];
		})
		
		try {
			var body = $("<div class='modal fade in' role='dialog' aria-hidden='true' style='z-index:100000'>");
			var modalDialog = $("<div class='modal-dialog'>");
			var modalContent = $("<div class='modal-content "+dialogClass+"'>");
			var modalBody = $("<div class='modal-body'>");
			var modalTitle = $("<div class='modal-title'>").append(title);
			var modalHeader = $("<div class='modal-header'>");
			var modalFooter= $("<div class='modal-footer'>");
			var modalClose = $('<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>');
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
			var body = $('<div class="dialog" style="z-index: 100000">').html(message);
			body.dialog($.extend(options, {
				resizable: false,
				modal: true,
				show: 'clip'
			}));
		}
	}
	
	this.notify = function (message, type, object) {
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
	}
	
	this.notifyInternal = function (newMessage, newClass, newObject) {
		var newMessage = new String(newMessage);
		switch(true)
		{
			case newObject instanceof HTMLElement:
			var obj = $(newObject).siblings('#alert');
			obj = obj.length <= 0 ? $(newObject).parents().find('#alert').last() : obj;
			break;
			
			case newObject instanceof Array:
			case newObject instanceof Object:
			var obj = $(newObject[0]).parents().find(newObject[1]).last();
			break;
			
			case newObject instanceof jQuery:
			var obj = newObject;
			break;
			
			case typeof newObject == 'string':
			var obj = this.getObj(newObject);
			break;
			
			default:
			var obj = this.getObj(this.responseSection);
			break;
		}
		if(obj instanceof jQuery)
		{
			var id = 'alert'+Date.now();
			var newClass = this.classes.hasOwnProperty(newClass) ? this.classes[newClass] : newClass;
			var message = $('<div id="'+id+'" class="'+newClass+'">').html(newMessage.toString());
			obj.append(message).fadeIn();
			setTimeout(function () {$('#'+id).fadeOut();$('#'+id).remove()}, 10000);
		}
		return obj;
	}
	
	this.updateSingle = function (uMsg, uClass, uApp, uID)
	{
		var uMessage = new String(uMsg);
		switch(uApp)
		{
			case true:
			this.getObj(uID).append("<span class='"+uClass+"'>"+uMessage+"</span>");
			break;
				
			default:
			this.getObj(uID).html("<span class='"+uClass+"'>"+uMessage+"</span>");
			break;
		}
	}
	
	this.clearNotify = function ()
	{
		this.getObj(this.responseSection).html("");
	}
	
	//function fo focus items with special box
	this.setFocus = function (item)
	{
		var obj = this.getObj(item);
		var orig_border = obj.css('border');
		obj.effect('pulsate', {times:2}, 'fast');
		obj.focus();
	}
	
	this.toggleElem = function (selector, by, by_val)
	{
		switch(typeof selector)
		{
			case 'string':
			case 'number':
				break;
				
			case 'object':
				selector = (selector.id == undefined) ? selector.name : selector.id;
				break;
				
			default:
				return false;
				break;
		}
		selector = this.this.jqEscape(selector);
		switch(by)
		{
			case 'name':
				selector = selector+' [name="'+by_val+'"]';
				break;
				
			case 'class':
				var obj = (selector[0] != '.') ? '.'+selector : selector;
				obj = '\\'+obj;
				break;
				
			default:
				selector = (selector[0] != '#') ? '#'+selector : selector;
				break
		}
		try {this.getObj(selector, '', false, false).each(function() {this.disabled = !this.disabled;})} catch(error) {};
	}
	
	this.addOnLoadEvent = function (func)
	{
		switch(document.readyState)
		{
			case 'complete':
			func();
			break;
				
			default:
			$(document).ready(func);
			break;
		}
	}
	
	this.handleVis = function (e, onlyShow)
	{
		switch(onlyShow)
		{
			case true:
			this.getObj(e).each(function () {
				if($(this).hasClass('hidden') && $(this).is(':hidden')) 
					$(this).css('display', 'none').removeClass('hidden');
				$(this).show('slow');
			});
			break;
			
			default:
			this.getObj(e).each(function () {
				if($(this).hasClass('hidden') && $(this).is(':hidden')) 
					$(this).css('display', 'none').removeClass('hidden');
				$(this).slideToggle('slow');
			});
			break;
		}
	}
	
	//get the object information
	this.getObj = function (selector, by, alert_obj, esc)
	{
		if(typeof selector == 'object')
			return $(selector);
		var esc = (esc == undefined) ? true : esc;
		if(selector instanceof jQuery)
		{
			try
			{
				switch(!selector.attr('id'))
				{
					case true:
						uniqueId = new Date().getTime();
						$(selector).attr('id', 'object'+uniqueId);
						break;
				}
			} catch (error) {};
			selector = selector.attr('id');
		} else if((typeof HTMLElement === "object" && selector instanceof HTMLElement) || //DOM2
			(selector && typeof selector === "object" && 
			selector !== null && selector.nodeType === 1 && 
			typeof selector.nodeName==="string")) {
			try
			{
				switch(!selector.id)
				{
					case true:
						uniqueId = new Date().getTime();
						selector.setAttribute('id', 'object'+uniqueId);
						break;
				}
			} catch (error) {};
			selector = selector.id;
		} else {
			switch(typeof selector)
			{
				case 'string':
				case 'number':
					break;
					
				default:
					return false;
					break;
			}
		}
		switch(selector)
		{
			case 'body':
			case 'document':
			case 'window':
			case document:
			case window:
			var obj = selector;
			break;
				
			default:
				selector = (esc === true) ? this.jqEscape(selector) : selector;
				if(selector[0] == '.') {
					by = 'class';
				}
				switch(by)
				{
					case 'name':
						var obj = '[name="'+selector+'"]';
						break;
					
					case 'class':
						var obj = (selector[0] != '.') ? '.'+selector : selector;
						obj = '\\'+obj;
						break;
						
					default:
						switch((selector[0] == '[') || (selector.indexOf(',') != -1))
						{
							case true:
								var obj = selector;
								break;
								
							default:
								var obj = (selector[0] != '#') ? '#'+selector : selector;
								break;
						}
						break
				}
				switch(alert_obj)
				{
					case true:
					alert(selector+" -> "+obj);
					break;
				}
				break;
		}
		return $(obj);
			
	}
	
	this.jqEscape = function (val) 
	{
		// return new String(val).replace(/[-[\]{}()*+?.,\\^$|]/g, '\\$&');
		return new String(val).replace(/[{}()*+?.,\\^$|]/g, '\\$&');
	}
	
	this.doRequest = function (options, rData, success, error, timeout, headers, useGet)
	{
		switch(this.r.hasOwnProperty('token'))
		{
			case true:
			this.r.beforeSend = function (xhr) {xhr.setRequestHeader("Authorization", "Basic "+this.r.token);};
			break;
		}
		switch(options instanceof Object)
		{
			case true:
			for(var property in options)
			{
				this.r[property] = options[property];
			}
			this.r.timeout = (options.hasOwnProperty('timeout')) ? options.timeout : 30000;
			break;
			
			default:
			this.r.url =  options;
			this.r.data =  rData;
			this.r.success =  success;
			this.r.timeout = timeout != undefined ? timeout : 30000;
			this.r.error = (error == undefined) ? function (e) { console.log(e) } : error;
			this.r.type = (useGet === true) ? 'GET' : 'POST';
			break;
		}
		var headers = (options instanceof Object) ? options.headers : headers;
		if(headers instanceof Object)
		{
			for(var key in headers)
			{
				this.r.beforeSend = function (xhr) {xhr.setRequestHeader(key, headers[key])};
			}
		}
		var ret_val = $.ajax(this.r);
		return ret_val;
	}
	
	this.doRequestFileData = function (_form, data)
	{
		//make sure the form is setup to send files
		this.$form.attr('enctype', "multipart/form-data");
		this.$form.attr('encoding', "multipart/form-data");
		
		// match anything not a [ or ]
		regexp = /^[^[\]]+/;
		
		//Deliver files with ajax submission
		var data = (data == undefined) ? new FormData() : data;
		this.$form.find(":file").each(function (i, file) {
			var fileInputName = regexp.exec(file.name);
			data.append(fileInputName+'['+i+']', file);
		});
		return data;
	}
	
	this.visibility = function (id, pour, caller)
	{
		data = {};
		data.get_html = false;
		data.for = pour;
		data.unique = id;
		var request_data = {0:"visibility", 1:data};
		var request = this.doRequest({'module':'api', 'proc':'procedure', 'data':request_data});
		request.done(function(result)
		{
			if(result)
			{
				var new_action = (result.data.hidden == 0) ? 'hide' : 'show';
				$(caller).text(new_action);
				switch(Number(result.data.hidden))
				{
					case 0:
						$(caller).parents("div[id='note_content"+id+"']").removeClass('hidden_displayed');
						break;
						
					default:
						$(caller).parents("div[id='note_content"+id+"']").addClass('hidden_displayed');
						break;
				}
			}
		});
	}
	
	this.place = function (newElem, data, addToElem, format, clear)
	{
		switch(typeof(newElem))
		{
			case 'object':
			var addTo = this.getObj(addToElem);
			var scrollToPos = 0;
			switch(format)
			{
				case 'text':
				var newElement = $('<div style="width:100%; padding:10px;" id="text_result"><br>'+data+'</div>');
				scrollToPos = newElement.get(0).id;
				break;
					
				default:
				var newElement = $(data);
				scrollToPos = newElement.get(0).id;
				break;
			}
			switch(typeof clear)
			{
				case 'string':
				addTo.find(clear).html('');
				break;
					
				case 'boolean':
				if(clear === true) {addTo.html('')};
				break;
			}
			if(newElem.prepend === true) {
				try 
				{
					switch(1)
					{
						case 1:
						switch(addTo.find(':first-child').attr('id'))
						{
							case 'noreplies':
								addTo.find(':first-child').remove();
								break;
						}
						newElement.appendTo(addTo);
						newElement.hide();
						break;
					}
					this.animateScroll(scrollToPos, addTo);
				}catch(error){}
			} else if(newElem.replace === true) {
				try 
				{
					addTo.replaceWith(data).effect('pulsate', {times:1}, 150);
					//this.animateScroll(scrollToPos, addTo);
				}catch(error){}
			} else {
				try 
				{
					switch(addTo.children().length)
					{
						case 0:
						addTo.append(newElement).next().hide();
						break;
							
						default:
						switch(addTo.find(':first-child').attr('id'))
						{
							case 'noreplies':
							addTo.find(':first-child').hide();
							newElement.prependTo('#'+addTo).hide();
							break;
								
							default:
							switch(newElem.index)
							{
								case -1:
								newElement.prependTo(addTo).hide();
								break;
									
								default:
								addTo.children().eq(newElem.index).after(newElement).next().hide();
								break;
							}
							break;
						}
						break;
					}
					this.animateScroll(scrollToPos, addTo);
				} catch(error){}
			}
			if(newElement != undefined)
				newElement.slideDown('fast');
			break;
		}
	}
	
	this.updateActivity = function (id) {
		if(id == undefined)
			return;
		if(this.hasActivity(id))
			self.getObj(id).removeData('_nitmToolsActivity');
		else {
			self.getObj(id).data('_nitmToolsActivity', true);
		}
	}
	
	this.hasActivity = function (id) {
		return (id != undefined) ?( self.getObj(id).data('_nitmToolsActivity') === true) : false;
	}
	
	this.activityId = function (id) {
		var $elem = self.getObj(id);
		return $elem.prop('tagName')+'-'+id;
	}
	
	this.safeFunctionName = function (input) {
		var array = new String(input).split('-');
		var string = $.map(array, function (value, index) {
			return value.ucfirst();
		});
		return string.join('');
	}
	
	/**
	 * Module related functions
	 */
	this.onModuleLoad = function(module, callback, namespace) {
		var ns = namespace == undefined ? '' : '.'+namespace;
		var event = 'nitm:'+module+ns;
		$('body').queue(event, function () {
			callback(self.module(module));
			$(this).dequeue(event)
		});
		switch(this.hasModule(module, false))
		{
			case true:
			this.moduleLoaded(module, namespace);
			break;
		}
	}
	
	this.moduleLoaded = function(module, namespace) {
		var ns = namespace == undefined ? '' : '.'+namespace;
		var event = 'nitm:'+module+ns;
		if($nitm.debug == true)
			console.log("Loaded "+module);
		$('body').dequeue(event);
	}
	
	this.module = function (name, defaultValue) {
		var found = false;
		var hierarchy = name.split(':');
		var index = this;
		for(var i in hierarchy)
		{
			if (index.hasOwnProperty('modules')) {
				index = index.modules;
			} 
			if(index.hasOwnProperty(hierarchy[i])) {
				index = index[hierarchy[i]];
				if(i == (hierarchy.length - 1)) {
					found = true;
					break;
				}
			}
		}
		ret_val = (found === true) ? index : defaultValue;
		return ret_val;
	}
	
	this.hasModule = function (name) {
		var ret_val = this.module(name, false) === false ? false : true;
		return ret_val;
	}
	
	this.setModule = function (module, name) {
		var name = this.getModuleName(module, name);
		var hierarchy = name.split(':');
		var moduleName = hierarchy.pop();
		var parent = (hierarchy.length == 0) ? self : this.module(hierarchy.join(':'));
		parent = parent==undefined ? self.modules : parent;
		if(!parent.hasOwnProperty('modules')) {
			parent['modules'] = {};
			Object.defineProperty(parent, 'modules', {
				'value': new Object,
				'enumerable': true
			});
		}
		Object.defineProperty(parent.modules, moduleName, {
			'value': module,
			'enumerable': true
		});
		this.moduleLoaded(name);
	}
	
	this.setCurrent = function (index) {
		if(index != undefined) {
			this.current = index;
		}
	}
	
	this.getModuleName = function (module, name) {
		if(name == undefined && (typeof module == 'object'))
			if(module.hasOwnProperty('id'))
				var name = module.id
			else
				var name = Date.now();
		return name;
	}
	
	this.initModule = function (object, name, defaults) {
		var name = this.getModuleName(object, name);
		switch(typeof object == 'object') {
			case true:
			switch(this.hasModule(name, false))
			{
				case false:
				this.current = name;
				this.setModule(object, name);
				switch(document.readyState)
				{
					case 'complete':
					self.initDefaults(name, object, defaults);
					break;
					
					default:
					$(document).ready(function () {
						self.initDefaults(name, object, defaults);
					});							
					break;
				}
				break;
				
				default:
				try {
					object.init();
				} catch(error) {}
				break;
			}
			break;
		}
	}
		
	this.initDefaults = function (key, object, defaults, container) {
		if (object == undefined)
			object = this.module(key);
		try {
			object.init();
		} catch (error) {}
		try {
			$.each(defaults, function () {
				try {
					object[this](container);
				} catch (error) {}
			});
		} catch (error) {}
	}
}

String.prototype.ucfirst = function() {
	return this.charAt(0).toUpperCase() + this.slice(1);
}

if (typeof String.prototype.parseFunction != 'function') {
    String.prototype.parseFunction = function () {
        var funcReg = /function *\(([^()]*)\)[ \n\t]*{(.*)}/gmi;
        var match = funcReg.exec(this.replace(/\n/g, ' '));

        if(match) {
            return new Function(match[1].split(','), match[2]);
        }

        return null;
    };
}

Array.prototype.unique = function() {
    var a = this.concat();
    for(var i=0; i<a.length; ++i) {
        for(var j=i+1; j<a.length; ++j) {
            if(a[i] === a[j])
                a.splice(j--, 1);
        }
    }

    return a;
};

$.fn.isBound = function(type, fn) {
    var data = this.data('events')[type];

    if (data === undefined || data.length === 0) {
        return false;
    }

    return (-1 !== $.inArray(fn, data));
};

$nitm = (window.$nitm == undefined) ? new Nitm() : $nitm;

/**
 * Setup some common event handlers here
 */
$($nitm).on('nitm-animate-submit-start', function (event, form) {
	$nitm.animateSubmit(form);
});
$($nitm).on('nitm-animate-submit-stop', function (event, form) {
	$nitm.animateSubmit(form, true);
});