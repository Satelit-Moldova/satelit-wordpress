/* [satelit_converter]: rates come from the page (data-satelit-rates, MDL per 1 unit); nothing is fetched. */
( function () {
	function fmt( n ) {
		try {
			return n.toLocaleString( document.documentElement.lang || undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
		} catch ( e ) {
			return n.toFixed( 2 );
		}
	}

	function setup( form ) {
		var rates;
		try {
			rates = JSON.parse( form.getAttribute( 'data-satelit-rates' ) );
		} catch ( e ) {
			return;
		}
		var amount = form.querySelector( '[name=amount]' );
		var from = form.querySelector( '[name=from]' );
		var to = form.querySelector( '[name=to]' );
		var out = form.querySelector( '.satelit-result' );
		var swap = form.querySelector( '.satelit-swap' );

		function calc() {
			var a = parseFloat( String( amount.value ).replace( ',', '.' ) );
			var f = rates[ from.value ];
			var t = rates[ to.value ];
			out.textContent = isNaN( a ) || ! f || ! t ? '—' : fmt( ( a * f ) / t ) + ' ' + to.value;
		}

		[ amount, from, to ].forEach( function ( el ) {
			el.addEventListener( 'input', calc );
			el.addEventListener( 'change', calc );
		} );
		if ( swap ) {
			swap.addEventListener( 'click', function () {
				var v = from.value;
				from.value = to.value;
				to.value = v;
				calc();
			} );
		}
		calc();
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( 'form[data-satelit-rates]' ), setup );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
