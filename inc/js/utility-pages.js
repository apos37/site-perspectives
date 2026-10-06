/**
 * SITE PERSPECTIVES: Utility pages
 *
 * Fills the Utility checkbox in Quick Edit from the flag on the row being edited.
 */

( function ( $ ) {
    'use strict';

    if ( ! window.inlineEditPost ) {
        return;
    }

    var originalEdit = window.inlineEditPost.edit;

    window.inlineEditPost.edit = function ( id ) {
        originalEdit.apply( this, arguments );

        var postId = 'object' === typeof id ? this.getId( id ) : id;
        var isUtility = '1' === String( $( '#post-' + postId + ' .siteperspectives-utility-flag' ).attr( 'data-utility' ) );

        $( '#edit-' + postId + ' input[name="siteperspectives_utility"]' ).prop( 'checked', isUtility );
    };
} )( jQuery );
