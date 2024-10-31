jQuery(function(){
	jQuery('[name=website],[name=utm_campaign],[name=utm_source],[name=utm_medium],[name=utm_term],[name=utm_content]').bind('change keyup',ns_link_builder.createURL);
	jQuery('[name=ctm]').bind('submit',function(){ return ns_link_builder.validateURL(); });
	jQuery('.ns-help-handle').tooltip({
		position:{my:"left top",at:"left bottom+2"}
	});
	jQuery('.ns-redirects-table').tooltip({
		items:'a',
		position:{my:"left top",at:"left bottom+10"},
		content:function(){return jQuery(this).next('.ns-tooltip-content').html();}
	});
	jQuery('.ns-delete-redirect').click(function(){
		return confirm( ns_link_builder_data.confirm_delete_msg );
	});
	jQuery('.ns-copy-redirect').click(function(){
		ns_link_builder.fillURL(
			jQuery(this).parent().prev().prev().prev().find('a').attr('href'),
			jQuery(this).parent().prev().prev().find('a').attr('href')
		);
		alert( ns_link_builder_data.explain_copy_msg );
	});
});

ns_link_builder = {
	
	validateURL : function(){
		if( !document.ctm ) return;
		var form = document.ctm;
		var required_params = ['utm_campaign','utm_source','utm_medium'];
		var friendly_url = document.getElementById('friendly_url').value;
		// ensure friendly url is not empty
		if( !friendly_url ){
			alert( ns_link_builder_data.missing_friendly_url_msg );
			return false;
		}
		// ensure friendly url (with or without trailing slash) has not been used before
		if( ns_link_builder_data.used_friendly_urls.indexOf( friendly_url.replace(/\/$/,'') ) != -1 ){
			alert( ns_link_builder_data.used_friendly_url_msg );
			return false;
		}
		// ensure the three necessary utm params are not empty
		for( var i=0; i<required_params.length; i++ ){
			var param = required_params[i];
			if( !form[ param ].value ){
				alert( ns_link_builder_data.missing_utm_msg+' '+param.replace('utm_','').replace('campaign','name')+'.');
				return false;
			}
		}
	},
	
	createURL : function() {
		if( !document.ctm ) return;
		var form = document.ctm;
		var url = '';
		var campaign_params = ['utm_campaign','utm_source','utm_medium','utm_term','utm_content'];
		
		// define initial site address
		if (form.website.value && form.website.value != "") {
			// add 'http://' to beginning if user left it out
			if (form.website.value.indexOf("http") != 0) {
				form.website.value = "http://" + form.website.value;
			}
			// add trailing slash if no params are on the end
			if (form.website.value.indexOf("/", 9) < 0 && form.website.value.indexOf("?") == -1) {
				form.website.value += "/";
			}
			// add ? or & to end depending on if url already has '?' in it or not
			url = form.website.value + ( form.website.value.indexOf("?")>=0? '&' : '?' );
		}
		
		// add all campaign / utm params
		var param_strings = [];
		for( var i=0; i<campaign_params.length; i++ ){
			var param = campaign_params[i];
			if( form[ param ].value && form[ param ].value != '' ){
				param_strings.push( param + "=" + encodeURIComponent(form[ param ].value) );
			}
		}
		url += param_strings.join('&');
		
		// set preview input value
		document.getElementById('campaign_url').value = url;
		return false;
	},
	
	fillURL : function( friendly_url, campaign_url ){
		if( !document.ctm ) return;
		var form = document.ctm;
		var provided_campaign_params = ns_link_builder.getQueryParameters( campaign_url );
		var all_campaign_params = ['utm_campaign','utm_source','utm_medium','utm_term','utm_content'];
		// set friendly & campaign url values
		document.getElementById('friendly_url').val = friendly_url;
		document.getElementById('campaign_url').val = campaign_url;
		// set individual utm fields based on values parsed out of campaign_url
		var campaign_link = document.createElement('a');
		campaign_link.href = campaign_url;
		for( var i=0; i<all_campaign_params.length; i++ ){
			var param = all_campaign_params[i];
			if( provided_campaign_params[ param ] ){
				form[ param ].value = decodeURIComponent( provided_campaign_params[ param ] );
			}
		}
	},
	
	getQueryParameters : function( str ){
		/* getQueryParameters.js - Copyright (c) 2014 Nicholas Ortenzio - The MIT License (MIT) */
		return (str || document.location.search).replace(/.*?(?=\?)/,'').replace(/(^\?)/,'').split("&").map(function(n){return n=n.split("="),this[n[0]]=n[1],this;}.bind({}))[0];
	}

};