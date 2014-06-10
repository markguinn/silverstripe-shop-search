// Easy expand/contract - see FacetTypeCheckboxInner for use. Classes:
// .expander - the link
// .expand-contrainer - toggled on the parent element (if has class "expanded" will start open)
// .for-expand/for-contract - within the expander link controls what content is visible
// .expander-target - the content that gets shown/hidden
$(document.body).on('click', 'a.expander', function(){
	$(this).closest('.expander-container').toggleClass('expanded');
});
