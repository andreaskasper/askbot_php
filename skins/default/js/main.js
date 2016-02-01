$(document).ready(function() {
	$("a[data-fancybox-type='iframe']").fancybox({
		maxWidth	: 800,
		maxHeight	: 600,
		width		: '70%',
		height		: '70%',
		openEffect	: 'none',
		closeEffect	: 'none',
		type		: 'iframe',
		iframe		: {	scrolling : 'auto',	preload   : false }
	});
	$("span.embedded.image").fancybox();
});

$.fn.api = function(action, vars, HandleSuccess, HandleError) {
	if (this.hasClass("disabled")) return;
	this.addClass("disabled").attr("DISABLED","DISABLED");
	var id = "Loading"+Math.floor(Math.random()*9999999999);
	var UrEle = this;
	this.after('<img src="styles/blue/img/loading.gif" id="'+id+'" alt="loading"/>');
	vars["format"] = "json";
	vars["action"] = action;
	$.post(APIURL, vars, function(data) {
		$("#"+id).remove();
		UrEle.attr("DISABLED","").removeClass("disabled").removeAttr("DISABLED");
		if (data.err.id == 0) {
			if (typeof(HandleSuccess) !== "undefined") HandleSuccess(data);
		} else {
			if (typeof(HandleError) == "undefined") ErrorBox("Fehler "+data.err.id,data.err.msg);
			else HandleError(data.err);
		}
	}, "json").error(function() { debug.log("API-Fehler!"); });
};

$.extend({ 
	api: function(action, vars, HandleSuccess, HandleError) {
		$.post(APIURL+action+".json", vars, function(data) {
			if (data.err.id == 0) {
				if (typeof(HandleSuccess) !== "undefined") HandleSuccess(data);
			} else {
				if (typeof(HandleError) == "undefined") ASI.MessageBoxError(data.err.msg, "Fehler "+data.err.id,"");
				else HandleError(data.err);
			}
		}, "json").error(function() { debug.log("API-Fehler!"); });
	} 
}); 

function ASI() {}
ASI.MessageBoxError = function(txt, title, icon) {
	var id = "Dialog_"+Math.floor(Math.random()*9999999999);
    $("body").append('<div id="'+id+'" title="'+title+'"><div class="icon '+icon+'"></div><p>'+txt+'</p></div>');
	$("#"+id).dialog({
		modal: false,
		dialogClass: "error",
		buttons: {
                "Ok": function() {
                    $( this ).dialog( "close" );
                }
            }
		});
	}
