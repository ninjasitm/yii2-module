
function Import()
{
	NitmEntity.call(this);
	
	var self = this;
	this.id = 'entity:import';
	
	this.forms = {
		roles: {
			create: "createImport",
			elementImport: "importElements"
		}
	};
	
	this.buttons = {
		create: 'newImport',
		remove: 'removeImport',
		disable: 'disableImport',
	};
	
	this.links = {
		source:  "[role~='importSource']"
	};
	
	this.views = {
		listFormContainerId: "[role~='importFormContainer']",
		containerId: "[role~='import']",
		itemId: "import",
		source: "[role~='sourceName']",
		sourceInput: "[role~='sourceNameInput']",
		preview: "[role~='previewImport']",
		element: "[role~='importElement']",
	};
	this.defaultInit = [
		'initForms',
		'initSource',
		'initPreview'
	];
	
	//functions
	this.initSource = function () {
		$(this.links.source).each(function () {
			var $elem = $(this);
			$elem.on('click', function (event) {
				$(self.views.source).each(function() {
					$(this).html($elem.data('source'));
				});
				$(self.views.sourceInput).each(function() {
					$(this).val($elem.data('source'));
				});
			});
		});
	}
	
	this.initPreview = function () {
		$("form[role~='"+this.forms.roles.create+"']").on('reset', function (event) {
			$(self.views.preview).empty();
		});
	}
	
	this.afterPreview = function(result, currentIndex, form) {
		$(this.views.preview).html(result.data);
		$(form).find(':submit').text("Import");
		$(form).find("table tbody.files").empty();
	}
	
	this.initElementImportForm = function (containerId){
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		container.find("form[role~='"+self.forms.roles.elementImport+"']").map(function() {
			var $form = $(this);
			$form.off('submit');
			$form.on('submit', function (e) {
				e.preventDefault();
				if(self.hasActivity(this.id))
					return false;
				self.updateActivity(this.id);
				$form.find(self.views.element).map(function () {
					self.importElement(this);
				});
			});
		});
	}
	
	this.afterElementImport = function (result, elem) {
		if(result.success) {
			var $elem = $(elem);
			$elem.parents('tr').addClass(this.classes[result.class]);
			$elem.addClass('disabled');
			$elem.html(result.icon);
			$elem.on('click', function (event) { event.preventDefault(); return false});
		}
	}
	
	this.initElementImport = function (containerId){
		var container = $nitm.getObj((containerId == undefined) ? 'body' : containerId);
		container.find(self.views.element).map(function() {
			var $elem = $(this);
			$elem.on('click', function (e) {
				e.preventDefault();
				$nitm.startSpinner($elem);
				self.importElement(elem);
			});
		});
	}
	
	this.importElement - function (element){
		var $elem = $(elem);
		$.post($elem.attr('href'), function(result) {
			$nitm.stopSpinner($elem);
			self.afterElementImport(result, $elem.get(0));
		});
	}
}

$nitm.addOnLoadEvent(function () {
	$nitm.initModule(new Import());
});