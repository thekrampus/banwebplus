function get_tab_by_tabname(s_tabname) {
	var a_tabs = $(".tab");
	var jtab = null;
	for(var i = 0; i < a_tabs.length; i++){
		if ($(a_tabs[i]).text() == s_tabname) {
			jtab = $(a_tabs[i]);
		}
	}
	return jtab;
}

function draw_tab(s_tabname) {
	// hide the previous tab
	var jprevious_tab = $(".tab.selected");
	if (jprevious_tab && jprevious_tab.length > 0) {
		var s_previous_tabname = jprevious_tab.text();
		var jprevious_tab_contents = $("#"+s_previous_tabname+".tab_contents_div");
		jprevious_tab_contents.stop(false,true);
		//jprevious_tab_contents.animate({opacity:0},500,function(){
			jprevious_tab_contents.hide();
		//});
	}
	// get the tab and it's contents
	var jtab = get_tab_by_tabname(s_tabname);
	var jtab_contents_container = $("#"+s_tabname+".tab_contents_div");
	var selected_button = jtab_contents_container.children("input[name='onselect']");
	jtab_contents_container.stop(false,true);
	jtab_contents_container.css({opacity:0});
	jtab_contents_container.show();
	jtab_contents_container.animate({opacity:1},500);
	// set the tab class
	var a_tabs = $(".tab");
	for(var i = 0; i < a_tabs.length; i++)
		$(a_tabs[i]).removeClass("selected");
	jtab.addClass("selected");
	if (selected_button.length > 0)
		selected_button.click();
}

function click_tab_by_tabname(s_tabname) {
	var jtab = get_tab_by_tabname(s_tabname);
	if (jtab !== null)
		jtab.click();
}