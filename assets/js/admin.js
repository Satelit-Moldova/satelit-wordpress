/* Settings → Satelit: "Test connection" calls admin-ajax (nonce checked server-side). */
( function ( cfg ) {
	var btn = document.getElementById( 'satelit-md-test' );
	var out = document.getElementById( 'satelit-md-test-result' );
	if ( ! btn || ! out || ! cfg ) {
		return;
	}
	btn.addEventListener( 'click', function () {
		var body = new window.FormData();
		body.append( 'action', 'satelit_md_test' );
		body.append( 'nonce', cfg.nonce );
		btn.disabled = true;
		out.textContent = cfg.testing;
		window.fetch( cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( j ) {
				out.textContent = ( j && j.data && j.data.message ) || cfg.failed;
				out.style.color = j && j.success ? '#00a32a' : '#d63638';
			} )
			.catch( function () {
				out.textContent = cfg.failed;
				out.style.color = '#d63638';
			} )
			.then( function () {
				btn.disabled = false;
			} );
	} );
}( window.satelitMdAdmin ) );
