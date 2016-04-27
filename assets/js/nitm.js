'use strict';
/*!
 * Nitm v1 (http://www.ninjasitm.com)
 * Copyright 2012-2014 NITM, Inc.
 */

class Nitm
{
	constructor () {
		this.i = 'nitm';
		this.events = {};
		this.current ='';
		this.modules = {};
		this.r = {'url':'/r/', 'type':'POST', 'dataType':'json'};
		this._ajaxEvents = [];
		this._defaultInited = [];
	}

	trigger (event, args) {
		$(document).trigger('nitm-event-'+event, args);
	}

	on (event, callback) {
		var self = this;
		$(document).on('nitm-event-'+event, callback);
	}

	addOnLoadEvent (func) {
		switch(document.readyState)
		{
			case 'complete':
			func.call();
			break;

			default:
			$(document).ready(func);
			break;
		}
	};

	//get the object information
	getObj (selector, by, alert_obj, esc)
	{
		if(typeof selector == 'object')
			return $(selector);
		esc = (esc === undefined) ? true : esc;
		let obj;
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
			} catch (error) {}
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
			} catch (error) {}
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
			obj = selector;
			break;

			default:
				selector = (esc === true) ? this.jqEscape(selector) : selector;
				if(selector[0] == '.') {
					by = 'class';
				}
				switch(by)
				{
					case 'name':
					obj = '[name="'+selector+'"]';
					break;

					case 'class':
					obj = (selector[0] != '.') ? '.'+selector : selector;
					obj = '\\'+obj;
					break;

					default:
					switch((selector[0] == '[') || (selector.indexOf(',') != -1))
					{
						case true:
						obj = selector;
						break;

						default:
						obj = (selector[0] != '#') ? '#'+selector : selector;
						break;
					}
					break;
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

	};

	jqEscape (val) {
		// return new String(val).replace(/[-[\]{}()*+?.,\\^$|]/g, '\\$&');
		return val.replace(/[{}()*+?.,\\^$|]/g, '\\$&');
	};

	doRequest (options, rData, success, error, timeout, headers, useGet) {
		let request = Object.assign({}, this.r);
		if(request.hasOwnProperty('token')) {
			request.beforeSend = function(xhr) {
				xhr.setRequestHeader("Authorization", "Basic "+request.token);
			};
		}
		if(options instanceof Object) {
			for(let property in options) {
				request[property] = options[property];
			}
			request.timeout = options.timeout || 30000;
		} else {
			request.url =  options;
			request.data =  rData;
			request.success =  success;
			request.timeout = timeout || 30000;
			request.error = error || function (e) {console.log(e);};
			request.type = (useGet === true) ? 'GET' : 'POST';
		}
		headers = (options instanceof Object) ? options.headers : headers;
		if(headers instanceof Object) {
			request.beforeSend = (function (xhr, headers) {
				for(let key in headers) {
					xhr.setRequestHeader(key, headers[key]);
				}
			})(headers);
		}
		return $.ajax(request);
	};

	doRequestFileData (form, data)
	{
		let $form = $(form);
		//make sure the form is setup to send files
		$form.attr('enctype', "multipart/form-data");
		$form.attr('encoding', "multipart/form-data");

		// match anything not a [ or ]
		regexp = /^[^[\]]+/;

		//Deliver files with ajax submission
		data = (data === undefined) ? new FormData() : data;
		$form.find(":file").each(function (i, file) {
			let fileInputName = regexp.exec(file.files[0].name);
			data.append(fileInputName+'['+i+']', file.files[0]);
		});
		return data;
	};

	safeFunctionName (input) {
		let array = input.split('-');
		let string = $.map(array, function (value, index) {
			return value.ucfirst();
		});
		return string.join('');
	};

	/**
	 * Module related functions
	 */
	onModuleLoad(module, callback, namespace) {
		return this.oml(module, callback, namespace);
	};

	oml(module, callback, namespace) {
		let ns = namespace === undefined ? '' : '.'+namespace;
		let event = 'nitm:'+module+ns;
		console.info("[Nitm]: Waiting for module: ("+module+")");
		this.queue(event, callback);
		if(this.hasModule(module, false))
			this.moduleLoaded(module, namespace);
	};

	queue(event, callback) {
		if(this.events[event] == undefined)
			this.events[event] = [];
		this.events[event].push(callback);
	}

	dequeue(event, module) {
		if(this.events.hasOwnProperty(event)) {
			this.events[event].forEach((callback) => {
				let moduleObject = this.m(module);
				callback.call(moduleObject, moduleObject);
			});
			delete this.events[event];
		}
	}

	moduleLoaded (module, namespace) {
		return this.ml(module, namespace);
	};

	ml(module, namespace) {
		let ns = namespace === undefined ? '' : '.'+namespace;
		let event = 'nitm:'+module+ns;
		this.dequeue(event, module);
	};

	module (name, defaultValue) {
		return this.m(name, defaultValue);
	};

	m(name, defaultValue) {
		let found = false;
		let hierarchy = name.split(':');
		let index = this;
		for(let i in hierarchy)
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
		let ret_val = (found === true) ? index : defaultValue;
		return ret_val;
	};

	hasModule (name) {
		return this.hm(name);
	};

	hm (name) {
		let ret_val = this.module(name, false) === false ? false : true;
		return ret_val;
	};

	setModule (module, name) {
		return this.sm(module, name);
	};

	sm(module, name) {
		name = this.getModuleName(module, name);
		let hierarchy = name.split(':');
		let moduleName = hierarchy.pop();
		let parent = (hierarchy.length === 0) ? this : this.module(hierarchy.join(':'));
		parent = parent === undefined ? this.modules : parent;
		if(!parent.hasOwnProperty('modules')) {
			parent.modules = {};
			Object.defineProperty(parent, 'modules', {
				'value': {},
				'enumerable': true
			});
		}
		if(!parent.modules.hasOwnProperty(moduleName)) {
			Object.defineProperty(parent.modules, moduleName, {
				'value': module,
				'enumerable': true
			});
		}
	};

	setCurrent (index) {
		if(index !== undefined) {
			this.current = index;
		}
	};

	getModuleName (module, name) {
		if(name === undefined && (typeof module == 'object'))
			if(module.hasOwnProperty('id'))
				name = module.id;
			else
				name = Date.now();
		return name;
	};

	initModule (object, name, defaults, sender) {
		name = this.getModuleName(object, name);
		switch(typeof object == 'object') {
			case true:
			console.info("[Nitm]: Module: ("+name+") was waiting for: ("+sender+")");
			switch(this.hasModule(name, false))
			{
				case false:
				this.current = name;
				$(document).ready(() => {
					console.info("[Nitm]: Initing module: ("+name+")");
					this.setModule(object, name);
					defaults = defaults || object.defaultInit;
					this.initDefaults(name, object, defaults, this.getContainer(object));
					this.moduleLoaded(object.id);
				});
				break;

				default:
				try {
					console.info("[Nitm]: Initing non local module: ("+name+")");
					object.init();
				} catch(error) {}
				break;
			}
			break;
		}
	};

	getContainer(object) {
		try {
			return object.getContainer();
		} catch(e) {
			return 'body';
		}
	}

	initDefaults (key, object, defaults, container) {
		if(this._defaultInited.indexOf(key+container) == -1) {
			console.info("[Nitm]: Initing defaults on ["+(container || 'body')+"] for ("+key+"): "+defaults);
			if (object === undefined)
				object = this.module(key);
			try {
				$.each(defaults, function () {
					if(typeof object[this] == 'function') {
						try {
							object[this].call(object, container, key);
						} catch (error) {
							throw (error);
							console.warn("[Nitm]: "+key+"->"+this+"() error: "+error.message+" in "+error.fileName+" on line "+error.lineNumber+":"+error.columnNumber);
						}
					} else
						console.warn("[Nitm]: Method: "+this+" doesn't exist on "+key);
				});
			} catch (error) {
				throw (error);
				console.warn("[Nitm]: Defaults should be a proper array. Received: "+defaults);
				try {
					object.init();
				} catch (error) {
					console.warn("[Nitm]: Empty defaults send to init but object has no init() method");
				}
			}
			this._defaultInited.push(key+container);
			console.info("[Nitm]: Completed init of ("+key+")");
		} else {
			console.warn("[Nitm]: Skipping defaults on ["+(container || 'body')+"] for ("+key+"): "+defaults);
		}
	};

	objectToSerializedArray(object, key) {
		let array = [];
		$.each(object, (prop, value) => {
			prop = (key !== undefined) ? key+'['+prop+']' : prop;
			if(typeof value == 'object')
				array = array.concat(this.objectToSerializedArray(value, prop));
			else {
				array.push({
					name: prop,
					value: value
				});
			}
		});
		return array;
	};

	wrapperId(event) {
		let wrapperId = $nitm.getObj(event.target).attr('id');
		if(wrapperId !== undefined)
			wrapperId = '#'+wrapperId;
		else
			wrapperId = 'body';
		return wrapperId;
	};

	realEvent(event) {
		return [event.type, event.namespace].filter(function (value) {
			return value != '';
		}).join('.');
	};

	initAjaxEvents(eventString) {
		if(eventString !== undefined) {
			let events = eventString.split(' '),
				uniqueEvents = [];
			eventString.split(' ').forEach((event) => {
				if(this._ajaxEvents.indexOf(event) === -1) {
					this._ajaxEvents.push(event);
					uniqueEvents.push(event);
				}
			});
			if(uniqueEvents.length) {
				$(document).ready(() => {
					$(document).on(uniqueEvents.join(' '), (event) => {
						console.info("[Nitm]: Preparing to wrap content after ajax event: "+this.realEvent(event));
						//Execute basic init on new contents
						$(document).trigger('nitm:ajax-event:'+this.realEvent(event), [event, this.wrapperId(event), $(event.target)]);
					});
				});
			}
		}
	}
}

String.prototype.ucfirst = function() {
	return this.charAt(0).toUpperCase() + this.slice(1);
};

if (typeof String.prototype.parseFunction != 'function') {
    String.prototype.parseFunction = function() {
        let funcReg = /function *\(([^()]*)\)[ \n\t]*{(.*)}/gmi;
        let match = funcReg.exec(this.replace(/\n/g, ' '));

        if(match) {
            return new Function(match[1].split(','), match[2]);
        }

        return null;
    };
}

Array.prototype.unique = function() {
    let a = this.concat();
    for(let i=0; i<a.length; ++i) {
        for(let j=i+1; j<a.length; ++j) {
            if(a[i] === a[j])
                a.splice(j--, 1);
        }
    }

    return a;
};

$.fn.isBound = function(type, fn) {
    let data = this.data('events')[type];

    if (data === undefined || data.length === 0) {
        return false;
    }

    return (-1 !== $.inArray(fn, data));
};

var $nitm = (window.$nitm === undefined) ? new Nitm() : $nitm;
$nitm.initAjaxEvents();
