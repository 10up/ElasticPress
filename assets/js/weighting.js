import jQuery from 'jquery';

jQuery( '.weighting-settings input[type=range]' ).change( function() {
	const $el = jQuery( this );

	$el.prev( 'label' ).find( '.weighting-value' ).text( $el.val() );

	$el.parents( 'fieldset' ).find( 'input[type="checkbox"]' ).prop( 'checked', true );
} );

jQuery( '.weighting-settings .searchable input[type=checkbox]' ).change( function() {
	const $checkbox = jQuery( this );
	const $rangeInput = $checkbox.parent().next( '.weighting' ).find( 'input[type=range]' );
	const $weightDisplay = $rangeInput.prev( 'label' ).find( '.weighting-value' );

	// toggle range input
	$rangeInput.prop( 'disabled', ! this.checked );

	if ( ! this.checked ) {
		$rangeInput.val( 1 );
	}

	if ( ! this.checked ) {
		$rangeInput.after( '<input type="hidden" name="' + $rangeInput.attr( 'name' ) + '" value="0" />' );
	} else {
		$rangeInput.parent().find( 'input[type="hidden"]' ).remove();
	}

	// get new weight display value, and set it
	const newWeightDisplay = !this.checked ? '0' : $rangeInput.val();
	$weightDisplay.text( newWeightDisplay );
} );
