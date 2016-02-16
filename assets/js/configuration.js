'use strict';

class Configuration extends NitmEntity
{
	constructor() {
		super('configuration');
		this.views = {
			containers: {
				section: 'sections_container',
				configuration: 'configuration_container',
				showSection: 'show_section',
				createValue: 'create_value_container',
				valueList: 'value-list',
			},
			alerts: 'configuration-alerts'
		};
		this.type = {
			default: 'db',
			current: 'db',
		};
		this.forms = {
			confirmThese: [
				'deleteSection',
				'deleteValue',
			],
			allowCreate: ['createNewValue', 'saveComment'],
			actions : {
				create: '/configuration/create',
				del: '/configuration/delete',
				update: '/configuration/update',
				undelete: '/configuration/undelete'
			}
		};
		this.buttons = {
			allowUpdate: ['updateFieldButton']
		};
		this.blocks = {
			allowUpdate: ['updateFieldDiv']
		};
		this.dropdowns = {
			submitOnChange: [
				'config_type',
				'config_container',
				'show_section'
			]
		};

		this.iObj = "_input";
		this.dm = 'configer';
		this.fromSession = true;
		this.defaultInit = [
			'initChanging',
		];
	}

	//functions
	initChanging () {
		this.dropdowns.submitOnChange.map((v) =>  {
			let $form = $('#'+v);
			switch(v)
			{
				case 'show_section':
				$form.off('submit');
				$form.on('submit', (e) =>  {
					e.preventDefault();
					this.operation(e.currentTarget);
				});
				break;
			}
			$form.find('select').on('change', (e) =>  {
				$form.submit();
			});
		});
	};

	initDeleting (containerId, result) {
		containerId = (containerId === undefined) ? 'body' : containerId;
		let $container = $nitm.getObj(containerId);
		this.forms.confirmThese.map((v) =>  {
			let $form = $container.find("form[role='"+v+"']");
			$form.off('submit');
			switch(v)
			{
				case 'deleteSection':
				if(result !== undefined)
					$form.find("input[id='configer\-section']").val(result.section);
				break;
			}
			$form.on('submit', (e) =>  {
				let $elem = $(e.currentTarget);
				e.preventDefault();
				let shouldConfirm = true, message;
				switch(v)
				{
					case 'deleteSection':
					let value = $form.find("input[id='configer\-section']").val();
					message = "Are you sure you want to delete section: "+value;
					break;

					case 'deleteValue':
					message = $elem.find(':submit').attr('title');
					shouldConfirm = false;
					break;
				}
				if(shouldConfirm) {
					if(confirm(message))
						this.operation(e.currentTarget);
				} else
					this.operation(e.currentTarget);
				return false;
			});
		});
	};

	initUpdating (containerId) {
		containerId = (containerId === undefined) ? 'body' : containerId;
		let $container = $nitm.getObj(containerId);
		this.buttons.allowUpdate.map((v) =>  {
			let $button = $container.find("[role='"+v+"']");
			$button.on('click', (e) =>  {
				e.preventDefault();
				let elem = $nitm.getObj($(e.currentTarget).data('id')).get(0);
				this.update(elem);
			});
		});

		this.blocks.allowUpdate.map((v) =>  {
			let block = $container.find("[role='"+v+"']"),
				fn = (e) => {
				e.preventDefault();
				this.update(e.currentTarget || e);
			};
			block.on('click', fn);
			block.data('action', fn);
		});
	};

	initCreating (containerId) {
		containerId = (containerId === undefined) ? 'body' : containerId;
		let $container = $nitm.getObj(containerId);
		this.forms.allowCreate.map((v) =>  {
			let $form = $container.find("form[role='"+v+"']");
			$form.off('submit');
			$form.on('submit', (e) =>  {
				e.preventDefault();
				this.operation(e.currentTarget);
			});
		});
	};

	afterGet(result) {
		let newClass = this.classes.warning, message;
		if(result.data) {
			message = !result.message ? 'Successfully loaded clean configuration information' : result.message;
			newClass = this.classes.success;
			let $container = $('#'+this.views.containers.section).html(result.data);
			this.initUpdating('#'+this.views.containers.configuration);
			this.initDeleting('#'+this.views.containers.configuration, result);
			this.initCreating('#'+this.views.containers.section);
			//this.initUpdating('#'+this.views.containers.section);
		}
		else {
			message = !result.message ? 'Error empty configuration information' : result.message;
		}
		$nitm.trigger('nitm:notify', [message, newClass, this.views.alerts]);
	};

	afterCreate(result, form) {
		let newClass = this.classes.warning;

		if(result.success)
			newClass = this.classes.success;

		$nitm.trigger('nitm:notify', [result.message, newClass, this.views.alerts]);
		let $form = $(form);
		switch($form.attr('role'))
		{
			case 'undeleteValue':
			if(result.success) {
				//if this value was recently deleted and is now re-createed then enabled deleting
				$form.find(':submit').removeClass('').addClass('btn btn-danger').html('del');
				$form.attr('action', this.forms.actions.del);
				$form.attr('role', 'deleteValue');
				$form.find(':input').removeAttr('disabled');
				$nitm.getObj('value_'+result.key).removeClass('disabled');
			}
			break;

			default:
			$nitm.getObj('#'+this.views.containers.valueList).append(result.data[2]);
			this.initDeleting('#'+'value_'+result.unique_id);
			this.initUpdating('#'+'value_'+result.unique_id);
			break;
		}
		$form.get(0).reset();
	};

	afterUpdate (result) {
		let newClass = this.classes.warning
			oldClass = this.classes.information;
		if(result.success)
			newClass = this.classes.success;
		else {
			oldClass = this.classes.warning;
			$nitm.getObj(result.key+'.div').html(result.old_value);
		}
		$nitm.getObj(result.key+'.div').removeClass().addClass(oldClass);
		$nitm.trigger('nitm:notify', [result.message, newClass, this.views.alerts]);
	};

	afterDelete(result, form) {
		let newClass = this.classes.warning;

		if(result.success)
			newClass = this.classes.success;

		$nitm.trigger('nitm:notify', [result.message, newClass, this.views.alerts]);
		if(result.success)
		{
			let $form = $(form);
			if(result.isSection) {
				$nitm.getObj('#'+this.views.containers.showSection).find("select :selected").remove();
				$nitm.getObj('#'+this.views.containers.valueList).html('');
			}
			else {
				let $button = $form.find(':submit');
				$button.removeClass().addClass('btn btn-warning');
				$button.html('undel').text('undel').attr('title', "Are you sure you want to undelete this value?");
				$form.attr('action', this.forms.actions.undelete);
				$form.attr('role', 'undeleteValue');
				$form.append("<input type='hidden' name='Configer[value\]' id='configer-value' value='"+$nitm.getObj(result.key+'.div').html()+"'/>");
				let $container = $nitm.getObj('value_'+result.key);
				$container.addClass('disabled');
				$container.children().map(function(i, elem) {
					let $elem = $(elem);
					if($elem.attr('role') != 'undeleteValue') {
						$elem.attr('disabled', true);
						$elem.addClass('disabled');
					}
				});
			}
		}
	};

	restore(form) {
		$form = $(form);
		try {
			let cellId = $form.find('input[name="cellId"]').val()
				oldData = $form.find('input[name="oldValue"]').val();
			$nitm.getObj(cellId).html(oldData.stripslashes());
		} catch(error) {}
		//$nitm.getObj(cellId).on('click', $nitm.getObj(cellId).data('action'));
	};

	parse(form) {
		let $form = $(form),
			cellId = $form.find('input[name="cellId"]').val(),
			inputId = $form.find('input[name="inputId"]').val(),
			oldData = $form.find('input[name="oldValue"]').val(),
			container = $form.find('input[name="container"]').val(),
			newData = $nitm.getObj(inputId).val(),
			newDataEnc = escape(newData),
			stop = false;
		if(!newData) {
			stop = true;
			$nitm.trigger('nitm:notify', ['Empty Data\nNo Update', 'alert', this.views.alerts]);
		}
		if(newData.localeCompare(oldData) === 0) {
			stop = true;
			$nitm.trigger('nitm:notify', ['Duplicate Data\nNo Update', 'alert', this.views.alerts]);
		}
		if (stop) {
			/*$input = $('<div id="'+cellId+'">'+newData+'</div>');
			 *	input.off('click');
			 *	input.on('click', () =>  {
			 *		this.update($nitm.getObj(cellId));
		});*/
			//$nitm.getObj(container).html(newData);
		}
		else {
			let obj = /^(\s*)([\W\w]*)(\b\s*$)/;
			if(obj.test(newData)) {
				newData = newData.replace(obj, '$2');
			}
			obj = /  /g;
			while(newData.match(obj)) {
				newData = newData.replace(obj, " ");
			}
			if(newData == 'NULL' || newData == 'null') {
				newData = '';
			}
			newData = newData.toString();
			$updateForm = $nitm.getObj('update_value_form_'+container);
			$updateForm.find("[role='value']").val(newData);
			this.operation($updateForm.get(0));
			$nitm.getObj(cellId).css('border','none');
			/*let $container = $nitm.getObj(cellId).html('<div id="'+cellId+'">'+newData+'</div>');
			 *	$container.off('click');
			 *	$container.on('click', () =>  {
			 *		this.update($nitm.getObj(cellId));
		});*/
		}
		$nitm.getObj(cellId).html(newData.stripslashes());
		//re-enable the onclick functionality
		$nitm.getObj(cellId).on('click', $nitm.getObj(cellId).data('action'));

	};

	update (elem) {
		let $elem = $nitm.getObj(elem),
			id = $elem.attr('id'),
			container = $elem.data('id'),
			type = $elem.data('type'),
			value = $elem.html(),
			oldValue = value.trim(),
			size = oldValue.length,
			style = 'font-weight:normal;font-size:12pt;',
			$input = null;
		switch(type)
		{
			case 'xml':
			style = 'font-weight:normal;font-size:12pt;';
			break;
		}
		let $form = $("<form name='activeForm' id='activeForm_"+container+"' class='form-horizontal' onsubmit='return false;'></form><br>");
		$form.append("<input type='hidden' name='container' value='"+container+"'>");
		$form.append("<input type='hidden' name='cellId' value='"+id+"'>");
		$form.append("<input type='hidden' name='inputId' value='"+id+this.iObj+"'>");
		$form.append("<input type='hidden' name='oldValue' value='"+oldValue+"'>");
		if(size > 96) {
			let cols = ($nitm.getObj(id).attr('offsetWidth') / 10) * 1.5,
				rows = Math.round(size/96) + Math.round((size/108)/8),
				$input = $('<textarea id="'+id+this.iObj+'" class="form-control" rows='+rows+'>'+value+'</textarea>');
			input.on('blur', () =>  {
				this.parse($form.get(0));
			});
			$form.append(input);
			$form.append("<br /><noscript><input value='OK' type='submit'></noscript>");
		} else {
			$input = $('<input class="form-control" size="'+size+'" type="text" id="'+id+this.iObj+'"\>');
			$input.val(value);
			$input.on('blur', () =>  {
				this.parse($form.get(0));
			});
			$form.append($input);
			//need to do this here because the input doesn't get recognized unless the form is closed out
			$form.append("<br /><noscript><input value='OK' type='submit'></noscript>");
			//then we can assign te value
			$nitm.getObj(id+this.iObj).attr('value', value);
		}
		$form.on('submit', (e) =>  {
			e.preventDefault();
			this.parse(e.currentTarget);
		});
		$nitm.getObj(elem.id).html('').append($form);
		//disable onclick functionality
		$nitm.getObj(elem.id).off('click');
		$nitm.getObj(elem.id+this.iObj).focus();
	};
}


String.prototype.trim = function () {
  return this.replace(/^\s*(\S*(\s+\S+)*)\s*$/, "$1");
};

String.prototype.createslashes = function() {
	return this.replace(/([\\\"\'\.])/g, "\\$1").replace(/\u0000/g, "\\0");
};

String.prototype.stripslashes = function () {
	return this.replace('/\0/g', '0').replace('/\(.)/g', '$1');
};

$nitm.onModuleLoad('entity', function (module) {
	module.initModule(new Configuration());
});
