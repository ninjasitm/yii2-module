'use strict';
/**
 * NITM Javascript Tools
 * Tools which allow some generic functionality not provided by Bootstrap
 * © NITM 2014
 */

class Tools {
    constructor() {
        this.id = 'tools';
        this.defaultInit = [
            'initVisibility',
            'initRemoveParent',
            'initDisableParent',
            'initCloneParent',
            'initDynamicDropdown',
            'initDynamicValue',
            'initAutocompleteSelect',
            'initSubmitSelect',
            'initConfirm',
            'initToolTips',
            'initEvents'
        ];
        this._activity = {};

        //Some fixes for some common widgets
        $(document).ready(() => {
            //this.initBsMultipleModal();
            this.initOffCanvasMenu();
        });
    }

    initDefaults(container) {
        $nitm.initDefaults(this.id, this, this.defaultInit, container);
    };

    initEvents() {

        $(document).on('pjax:success loaded.bs.modal', (data, status, xhr, options) => {
            console.info("[Nitm: Tools]: Running helper scripts after event (" + data.type + "." + data.namespace + ") on HTML element: #" + data.target.id);
            $nitm.initDefaults(this.id, this, this.defaultInit, '#' + data.target.id);
        });
    }

    /**
     * Submit a form on change of dropdown input
     * @param string containerId
     */
    initSubmitSelect(containerId) {
        //May not be necesary when using Bootstrap Nav menu
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        $container.find("[role~='changeSubmit']").map((e) => {
            let $elem = $(e.currentTarget);
            if (!$elem.data('nitm-entity-change')) {
                $elem.data('nitm-entity-change', true);
                $elem.off('change');
                $elem.on('change', function(event) {
                    window.location.replace($(event.target).val());
                });
            }
        });
    };

    /**
     * Use data attributes to load a URL into a container/element
     * @param string containerId
     */
    initVisibility(containerId) {
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        //enable hide/unhide functionality with optional data retrieval
        $container.find("[role~='visibility']").map((i, e) => {
            let $target = $(e);
            if ($target.data('id') !== undefined) {
                let events = $target.data('events') || 'click';
                $.each(events.split(','), (index, eventName) => {
                    if (!$target.data('nitm-entity-' + eventName)) {
                        $target.data('nitm-entity-' + eventName, true);
                        let _callback = (e) => {
                            e.preventDefault();
                            return this.visibility(e.target);
                        };
                        if ($target.data('run-once'))
                            $target.one(eventName, _callback);
                        else
                            $target.on(eventName, _callback);
                    }
                });
            }
        });
    };

    /**
     * Change the visibility of an element(s)
     * @param  jQuery|HTMLElement      [description]
     * @param  boolean removeListener [description]
     * @return Promise                [description]
     */
    visibility(object, removeListener) {
        return new Promise((resolve, reject) => {
            $nitm.trigger('animate-submit-start', [object, '...']);
            let $object = $nitm.getObj(object);
            let on = $object.data('on');
            let getUrl = true;
            let url = !$object.data('url') ? $object.attr('href') : $object.data('url');

            if ($object.data('on') !== undefined)
                if ($object.data('on').length === 0) getUrl = false;

            let getRemote = function() {
                let isBasedOnGetUrl = (url !== undefined) && (url != '#') && (url.length >= 2) && getUrl;
                let isBasedOnRemoteOnce = ($object.data('remote-once') !== undefined) ? (Boolean($object.data('remote-once')) && !$object.data('got-remote')) : true;
                return isBasedOnGetUrl && isBasedOnRemoteOnce;
            };

            if (getRemote()) {
                let success = $object.data('success') || null;
                let ret_val = $.ajax({
                    url: url,
                    type: ($object.data('method') !== undefined) ? $object.data('method') : 'get',
                    dataType: $object.data('type') ? $object.data('type') : 'html',
                    success: success,
                    complete: (result) => {
                        this.replaceContents(result.responseText, object, $nitm.getObj($object.data('id')));
                    }
                });
                $object.data('got-remote', true);
            }

            $nitm.trigger('animate-submit-stop', [object]);

            $nitm.m('utils')
                .handleVis($object.data('id'))
                .then(() => {
                    if ($object.data('toggle-inputs'))
                        $nitm.getObj($object.data('id')).find(':input').prop('disabled', function(i, v) {
                            return !v;
                        });
                    resolve();
                });
        });
    };

    replaceContents(result, object, target) {
        let $object = $nitm.getObj(object);
        if ($object.data('toggle')) {
            $nitm.m('utils').handleVis($object.data('toggle'))
                .then(() => {
                    this.evalScripts(result, function(responseText) {
                        target.html(responseText);
                    });
                }, function(error) {
                    console.info('[Nitm: Tools]: replaceContents() Error: ' + error);
                });
        } else {
            this.evalScripts(result, function(responseText) {
                target.html(responseText);
            });
        }
    };

    /**
     * Populate another dropdown with data from the current dropdown
     */
    initDynamicDropdown(containerId) {
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        $container.find("[role~='dynamicDropdown']").map((i, e) => {
            let $target = $(e);
            if (e.id !== undefined) {
                if (!$target.data('nitm-entity-change')) {
                    $target.data('nitm-entity-change', true);
                    $target.off('change');
                    $target.on('change', function(e) {
                        e.preventDefault();
                        let $element = $(e.currentTarget);
                        let url = $element.data('url');
                        if ((url != '#') && (url.length >= 2)) {
                            $element.removeAttr('disabled');
                            $element.empty();
                            $.get(url + $element.find(':selected').val())
                                .done(function(result) {
                                    result = $.parseJSON(result);
                                    $element.append($('<option></option>').val('').html('Select value...'));
                                    if (typeof result == 'object') {
                                        $.each(result, function(val, text) {
                                            $element.append($('<option></option>').val(text.value).html(text.label));
                                        });
                                    }
                                }, 'json');
                        }
                        return true;
                    });
                }
            }
        });
    };

    /**
     * Set the value for an element using data attributes
     */
    initDynamicValue(containerId) {
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        //enable hide/unhide functionality with optional data retrieval
        $container.find("[role~='dynamicValue']").map((i, e) => {
            let $target = $(e);
            if (($target.data('id') !== undefined) || ($target.data('type') !== undefined)) {
                let events = $target.data('events') || 'click';
                $.each(events.split(','), (index, eventName) => {
                    if (!$target.data('nitm-entity-' + eventName)) {
                        $target.data('nitm-entity-' + eventName, true);
                        let _callback = (e) => {
                            e.preventDefault();
                            this.dynamicValue($(e.currentTarget).get(0));
                            return true;
                        };
                        if ($target.data('run-once'))
                            $target.one(eventName, _callback);
                        else
                            $target.on(eventName, _callback);
                    }
                });
            }
        });
    };

    /**
     * Set a value dynamically
     * @param  string|HTMLElement|jQuery object
     * @return Promise
     */
    dynamicValue(object) {
        return new Promise((resolve, reject) => {
            let $object = $nitm.getObj(object);
            object = $object.get(0);

            $nitm.m('utils').hasNoActivity(object.id)
                .then(() => {
                    $nitm.trigger('animate-submit-start', [object]);
                    $nitm.m('utils').updateActivity(object.id);

                    let $target = $nitm.getObj($object.data('id'));
                    let $element = !$target.length ? $object : $target;

                    if ($element.data('run-once') && ($element.data('run-times') >= 1)) {
                        reject();
                    }

                    let url = !$object.data('url') ? $object.attr('href') : $object.data('url');
                    let on = $object.data('on');

                    if ($(on).get(0) === undefined) {
                        reject(false);
                    }

                    if ((url != '#') && (url.length >= 2)) {
                        $element.removeAttr('disabled').empty();
                        let selected = $object.find(':selected').val() || -1;

                        let ajaxSettings = {
                            url: url + selected,
                            method: $object.data('method') || $object.data('ajaxMethod') || 'get',
                        };
                        let success = null;
                        switch ($object.data('type')) {
                            case 'html':
                                Object.assign(ajaxSettings, {
                                    dataType: 'html',
                                });
                                success = (result) => {
                                    this.evalScripts(result, function(responseText) {
                                        $element.html(responseText);
                                    });
                                };
                                break;

                            case 'callback':
                                Object.assign(ajaxSettings, {
                                    dataType: 'json'
                                });
                                success = (result) => {
                                    eval("let callback = " + $object.data('callback'));
                                    callback.call(this, [result, $element.get(0)]);
                                };
                                break;

                            default:
                                Object.assign(ajaxSettings, {
                                    dataType: 'text',
                                });
                                success = function(result) {
                                    $element.val(result);
                                };
                                break;
                        }
                        $.ajax(ajaxSettings)
                            .always(function(result, status, xhr) {
                                $nitm.trigger('animate-submit-stop', [object]);
                            }).done((result, status, xhr) => {
                                $element.data('run-times', 1);
                                $nitm.m('utils').updateActivity(object.id);
                                success.call(this, result);
                                resolve(result);
                            }).fail(function(xhr, status, error) {
                                $nitm.trigger('indicate', [error, object]);
                                reject();
                            });
                    }
                });
        });
    };


    /**
     * Set the value for an element using data attributes
     */
    initDynamicIframe(containerId) {
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        //enable hide/unhide functionality with optional data retrieval
        $container.find("[role~='dynamicIframe']").map((i, e) => {
            let $target = $(e);
            if (($target.data('id') !== undefined)) {
                let events = $target.data('events') || 'click';
                $.each(events.split(','), function(index, eventName) {
                    if (!$target.data('nitm-entity-' + eventName)) {
                        $target.data('nitm-entity-' + eventName, true);
                        if ($target.data('run-once')) {
                            $target.one(eventName, function(e) {
                                e.preventDefault();
                                this.dynamicIframe(e.currentTarget);
                            });
                        } else {
                            $target.on(eventName, function(e) {
                                e.preventDefault();
                                this.dynamicIframe(e.currentTarget);
                            });
                        }
                    }
                });
            }
        });
    };

    dynamicIframe(object) {
        return new Promise(function(resolve, reject) {
            let $object = $nitm.getObj(object);
            object = $object.get(0);
            let $target = $nitm.getObj($object.data('id'));
            if (($indicator = $nitm.getObj($object.data('indicator'))).get(0) === undefined)
                $indicator = $object;

            $indicator.text('Loading...').fadeIn();
            $target.fadeOut();
            let url = $object.attr('href');

            if ($nitm.m('utils').hasActivity(object.id))
                return;

            $nitm.trigger('animate-submit-start', [$indicator.get(0)]);
            $target.attr('src', ($object.data('url') || $object.attr('href')));
            $target.load(function() {
                $elem.fadeIn();
                $indicator.fadeOut();
            });
            $nitm.trigger('animate-submit-stop', [$indicator.get(0)]);
        });
    };

    /**
     * THis is used to evaluate remote js files returned in ajax calls
     */
    evalScripts(text, callback, options) {
        let dom = $(text);
        if (typeof callback == 'function') {
            //We do this here so that the js gets loaded ONLY after the ajax calls are done
            $(document).one('ajaxStop', () => {
                console.info("[Nitm->Tools]: Running evalScripts after ajaxStop event");
                let existing, wrapperId, wrapper;
                if (options !== undefined) {
                    existing = (!options.context) ? false : options.context.attr('id');
                } else {
                    existing = false;
                }
                wrapperId = !existing ? false : $nitm.getObj(existing).find("[role='nitmToolsAjaxWrapper']").attr('id');
                if (wrapperId) {
                    wrapper = $('#' + wrapperId);
                    wrapper.html('').html(dom.html());
                } else {
                    wrapperId = 'nitm-tools-ajax-wrapper' + Date.now();
                    wrapper = $('<div id="' + wrapperId + '" role="nitmToolsAjaxWrapper">');
                    wrapper.append(dom);
                }
                let contents = $('<div>').append(wrapper);
                //Execute basic init on new content
                let promise = new Promise((resolve, reject) => {
                    try {
                        callback(contents.html());
                    } catch (error) {
                        throw (error);
                    }
                    resolve();
                }).then(() => {
                    console.info("[Nitm: Tools]: Initing in evalScripts defaults on #" + wrapperId);
                    $nitm.initDefaults(this.id, this, this.defaultInit, wrapperId);
                });
            });
        }
    };

    /**
     * Remove the parent element up to a certain depth
     */
    initRemoveParent(containerId) {
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        //enable hide/unhide functionality
        $container.find("[role~='removeParent']").map((i, e) => {
            let $elem = $(e);
            if (!$elem.data('nitm-entity-click')) {
                $elem.data('nitm-entity-click', true);
                $elem.on('click', (e) => {
                    e.preventDefault();
                    this.removeParent(e.target);
                    return true;
                });
            }
        });
    };

    /**
     * Remove the parent element up to a certain depth
     */
    initCloneParent(containerId) {
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        //enable hide/unhide functionality
        $container.find("[role~='cloneParent']").map((i, e) => {
            let $elem = $(e);
            if (!$elem.data('nitm-entity-click')) {
                $elem.data('nitm-entity-click', true);
                $elem.on('click', function(e) {
                    e.preventDefault();
                    this.cloneParent(e.currentTarget);
                    if ($(e.currentTarget).data('propagate') === undefined)
                        $(e.currentTarget).click();
                    return true;
                });
            }
        });
    };

    /**
     * Remove the parent element up to a certain depth
     */
    removeParent(elem) {
        return new Promise(function(resolve, reject) {
            let $elem = $(elem);
            let $parent = null;
            let levels = $elem.data('depth') || -1;
            if ($elem.data('parent') !== undefined) {
                $parent = $elem.parents($elem.data('parent')).eq(levels);
                if (!$parent.length)
                    $parent = $elem.parents($elem.data('parent'));
            } else if (levels)
                $parent = $elem.parents().eq(levels);
            if ($parent.length)
                $parent.hide('slow').remove();
            resolve();
        });
    };

    /**
     * Remove the parent element up to a certain depth
     */
    cloneParent(elem, callbacks) {
        return new Promise(function(resolve, reject) {
            let $element = $(elem);
            let clone = $nitm.getObj($element.data('clone')).clone();
            clone.find('input').not(':hidden').val('');
            let currentId = !clone.attr('id') ? clone.prop('tagName') : clone.attr('id');
            clone.attr('id', currentId + Date.now());
            let to = $nitm.getObj($element.data('to'));

            if (typeof callbacks.before == 'function') {
                clone = callbacks.before(clone, to, elem);
            } else if ($element.data('before-clone') !== undefined) {
                eval("let beforeClone = " + $element.data('before-clone'));
                clone = beforeClone(clone, to, elem);
            }

            if ($element.data('after') !== undefined) {
                clone.insertAfter(to.find($element.data('after')));
            } else if ($element.data('before') !== undefined) {
                clone.insertBefore(to.find($element.data('before')));
            } else {
                to.append(clone);
            }

            if (typeof callbacks.after == 'function') {
                callbacks.after(clone, to, elem);
            } else if ($element.data('after-clone') !== undefined) {
                eval("let afterClone = " + $element.data('after-clone'));
                afterClone(clone, to, elem);
            }
            resolve(clone);
        });
    };

    /**
     * Initialize remove parent elements
     */
    initDisableParent(containerId) {
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        //enable hide/unhide functionality
        $container.find("[role~='disableParent']").map((i, e) => {
            let $elem = $(e);
            if (!$elem.data('nitm-entity-click')) {
                $elem.data('nitm-entity-click', true);
                $elem.on('click', (e) => {
                    e.preventDefault();
                    this.disableParent(e.currentTarget);
                });
            }
        });
    };


    /**
     * Disable the parent element up to a certain depth
     */
    disableParent(elem, levels, parentOptions, disablerOptions, dontDisableFields) {
        return new Promise(function(resolve, reject) {
            let $element = $(elem);
            let $parent = null;
            if ($element.data('parent') !== undefined)
                $parent = $nitm.getObj($element.data('parent'));
            else {
                levels = ($element.data('depth') === undefined) ? ((levels === undefined) ? 1 : levels) : $element.data('depth');
                $parent = $element.parent();
                for (i = 0; i < levels; i++) {
                    $parent = parent.parent();
                }
            }
            //If we're dealing with a form, start from the submit button
            switch ($element.prop('tagName')) {
                case 'FORM':
                    elem = $element.find(':submit').get(0);
                    break;
            }

            /*
             * For some reason this cdoe block doesn't make sense...
             */
            $element.attr('role', 'disableParent');
            //get and set the role of the element activating this removal process
            let thisRole = $element.attr('role')
            let disabled = false;
            $element.attr('role', (thisRole === undefined) ? 'disableParent' : thisRole);
            thisRole = $element.attr('role');

            //get and set the disabled data attribute
            switch ($element.data('disabled')) {
                case 1:
                case true:
                    disabled = 1;
                    break;

                default:
                    disabled = ($element.data('disabled') === undefined) ? 1 : 0;
                    break;
            }
            $element.data('disabled', !disabled);

            let _defaultDisablerOptions = {
                size: !$element.attr('class') ? 'btn-sm' : $element.attr('class'),
                indicator: ((disabled == 1) ? 'refresh' : 'ban'),
                class: $element.attr('class')
            };
            //change the button to determine the curent status
            let _disablerOptions = {};
            for (let attribute in _defaultDisablerOptions) {
                try {
                    _disablerOptions[attribute] = (disablerOptions.hasOwnProperty(attribute)) ? disablerOptions[attribute] : _defaultDisablerOptions[attribute];
                } catch (error) {
                    _disablerOptions[attribute] = _defaultDisablerOptions[attribute];
                }

            }
            $element.removeClass().addClass(_disablerOptions.class + ' ' + _disablerOptions.size).html("<span class='fa fa-" + _disablerOptions.indicator + "'></span>");

            //now perform disabling on parent
            let _defaultParentOptions = {
                class: ((disabled == 1) ? 'bg-disabled' : 'bg-success')
            };
            let elemEvents = 'click',
                _class, _icon;
            let trigger = function(event) {
                $elem.trigger(event);
            };
            let triggerFalse = function(event) {
                return false;
            };
            $parent.find(':input,:button,a').map(function(i, input) {
                let $input = $(input);
                switch ($input.attr('role')) {
                    case thisRole:
                        break;

                    default:
                        if (!$input.data('keep-enabled') || ($input.attr('name') !== '_csrf')) {
                            let _class = 'warning';
                            let _icon = 'plus';
                            if (disabled) {
                                _class = 'danger';
                                _icon = 'ban';
                            }
                            if (!dontDisableFields) {
                                for (let event in elemEvents) {
                                    let func = disabled ? triggerFalse(event) : trigger(event);
                                    $input.on(event, func);
                                }
                                if (disabled)
                                    $input.attr('disabled', disabled);
                                else
                                    $input.removeAttr('disabled');
                            }
                        }
                        break;
                }
            });

            let _parentOptions = {};
            for (let _attribute in _defaultParentOptions) {
                try {
                    _parentOptions[_attribute] = (parentOptions.hasOwnProperty(_attribute)) ? parentOptions[_attribute] : _defaultParentOptions[_attribute];
                } catch (error) {
                    _parentOptions[_attribute] = _defaultParentOptions[_attribute];
                }

            }
            parent.removeClass().addClass(_parentOptions.class);
            resolve();
        });
    };

    /**
     * Fix for loading multiple boostrap modals
     */
    initBsMultipleModal() {
        //to support multiple modals
        $(document).on('hidden.bs.modal', function(e) {
            $(e.currentTarget).removeData('bs.modal');
            //Fix a bug in modal which doesn't properly reload remote content
            $(e.currentTarget).find('.modal-content').html('');
        });
    };

    /**
     * Custom auto complete handler
     */
    initAutocompleteSelect(containerId) {
        let $container = $nitm.getObj((!containerId) ? 'body' : containerId);
        $container.find("[role~='autocompleteSelect']").each(function() {
            $(this).on('autocompleteselect', function(e, ui) {
                e.preventDefault();
                let $elem = $(e.currentTarget);
                let element = $elem.data('real-input');
                let appendTo = $elem.data('append-html');
                if (appendTo !== undefined)
                    if (ui.item.html !== undefined)
                        $nitm.getObj(appendTo).append($(ui.item.html));

                if (element !== undefined) {
                    $nitm.getObj(element).val(ui.item.value);
                    $elem.val(ui.item.text);
                } else {
                    $elem.val(ui.item.value);
                }
            });
        });
    };

    /**
     * Off canvas menu support
     */
    initOffCanvasMenu() {
        $(document).ready(function() {
            $("[data-toggle='offcanvas']").click(function() {
                $('.row-offcanvas').toggleClass('active');
            });
        });
    };

    initConfirm() {
        $(document).ready(function() {
            $('[data-confirm]').on('click', function(event) {
                if (!confirm($elem.data('confirm'))) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                } else {
                    return true;
                }
            }).each(function() {
                let listeners = $._data(this, "events").click;
                listeners.reverse();
            });
        });
    };

    /**
     * Off tooltip support
     */
    initToolTips() {
        try {
            $(document).ready(function() {
                $("body").tooltip({
                    selector: '[data-toggle=tooltip]'
                });
            });
        } catch (error) {}
    };
}

$nitm.addOnLoadEvent(function() {
    $nitm.initModule(new Tools());
});
