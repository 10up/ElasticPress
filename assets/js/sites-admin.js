import jQuery from 'jquery';
import {epsa} from 'window';

window.addEventListener( 'load', function() {
	const toggles = document.getElementsByClassName( 'index-toggle' );
	for ( let i = 0; i < toggles.length; i++ ) {
		toggles[i].addEventListener( 'click', function() {

			const checked = ( this.checked ) ? 'yes' : 'no';

			jQuery.get( epsa.ajax_url,
				{
					action: 'ep_site_admin',
					blog_id: this.dataset.blogid,
					nonce: epsa.nonce,
					checked: checked,
				}, () => {
					document.getElementById(
						`switch-label-${ 
							this.dataset.blogid}` ).innerHTML = ( this.checked ) ?
						'On' :
						'Off';
				} );

		} );

	}
} );

