/**
 * SITE PERSPECTIVES: Frontend and Backend tree tabs
 *
 * Expand/collapse, filtering, vertical/horizontal switch, display options, inline edit mode
 * with drag and drop, adding content, icon picker and AJAX saving.
 */

( function ( $ ) {
    'use strict';

    var config = window.sitePerspectivesTree || {};
    var app;
    var editing = false;
    var dirty = false;
    var snapshot = {};
    var metaKeysLoaded = false;
    var optionsTimer;
    var searchTimer;
    var iconTarget = null;
    var iconGridHtml = '';

    var sortableOptions = {
        connectWith: '.siteperspectives-list',
        items: '> li.siteperspectives-node',
        handle: '.siteperspectives-handle',
        placeholder: 'siteperspectives-placeholder',
        forcePlaceholderSize: true,
        tolerance: 'pointer',
        cursor: 'grabbing',
        distance: 3,
        update: function () {
            setDirty();
        },
        start: function () {
            app.addClass( 'siteperspectives-dragging' );
        },
        stop: function () {
            app.removeClass( 'siteperspectives-dragging' );
            updateEmpty();
        }
    };

    $( document ).ready( function () {
        app = $( '#siteperspectives-app' );

        if ( ! app.length || ! config.tab ) {
            return;
        }

        bindViewControls();
        bindOptions();
        bindEditControls();
        bindAddPanel();
        bindIconPicker();
        updateEmpty();
    } );


    /**
     * Send an AJAX request with the shared nonce and tab.
     *
     * @param {string} action
     * @param {Object} data
     * @return {jqXHR}
     */
    function request( action, data ) {
        return $.ajax( {
            url: config.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: $.extend( { action: action, nonce: config.nonce, tab: config.tab }, data )
        } );
    } // End request()


    /**
     * Expand/collapse, search and orientation.
     */
    function bindViewControls() {
        app.on( 'click', '.siteperspectives-toggle', function () {
            toggleCard( $( this ).closest( '.siteperspectives-card' ), null );
        } );

        app.on( 'click', '.siteperspectives-title-link', function () {
            var button = $( this );
            var box = button.siblings( '.siteperspectives-inline-url' );
            var open = box.prop( 'hidden' );

            box.prop( 'hidden', ! open );
            button.attr( 'aria-expanded', open ? 'true' : 'false' );

            if ( open ) {
                box.find( 'input' ).trigger( 'focus' ).trigger( 'select' );
            }
        } );

        $( '#siteperspectives-expand-all' ).on( 'click', function () {
            app.find( '.siteperspectives-card' ).each( function () {
                toggleCard( $( this ), true );
            } );
        } );

        $( '#siteperspectives-collapse-all' ).on( 'click', function () {
            app.find( '.siteperspectives-card' ).each( function () {
                toggleCard( $( this ), false );
            } );
        } );

        $( '.siteperspectives-orientation button[data-orientation]' ).on( 'click', function () {
            var value = $( this ).data( 'orientation' );

            $( this ).addClass( 'is-active' ).siblings().removeClass( 'is-active' );
            app.attr( 'data-orientation', value );
            request( 'siteperspectives_save_orientation', { orientation: value } );
        } );

        $( '#siteperspectives-search' ).on( 'input', function () {
            var value = $.trim( $( this ).val() ).toLowerCase();

            clearTimeout( searchTimer );
            searchTimer = setTimeout( function () {
                filterTree( value );
            }, 120 );
        } );
    } // End bindViewControls()


    /**
     * Expand or collapse a card's details.
     *
     * @param {jQuery} card
     * @param {boolean|null} force
     */
    function toggleCard( card, force ) {
        var button = card.find( '.siteperspectives-toggle' ).first();
        var panel = card.children( '.siteperspectives-expanded' );
        var open = ( null === force ) ? ( 'true' !== button.attr( 'aria-expanded' ) ) : force;

        button.attr( 'aria-expanded', open ? 'true' : 'false' );
        panel.prop( 'hidden', ! open );
    } // End toggleCard()


    /**
     * Filter nodes by title or collapsed data, keeping ancestors of matches visible.
     *
     * @param {string} value
     */
    function filterTree( value ) {
        var nodes = app.find( 'li.siteperspectives-node' );

        nodes.removeClass( 'siteperspectives-filter-hide' );

        if ( '' === value ) {
            return;
        }

        nodes.addClass( 'siteperspectives-filter-hide' );

        nodes.each( function () {
            var node = $( this );
            var text = node.children( '.siteperspectives-card' ).find( '.siteperspectives-title, .siteperspectives-collapsed-data' ).text().toLowerCase();

            if ( text.indexOf( value ) !== -1 ) {
                node.removeClass( 'siteperspectives-filter-hide' );
                node.parents( 'li.siteperspectives-node' ).removeClass( 'siteperspectives-filter-hide' );
                node.find( 'li.siteperspectives-node' ).removeClass( 'siteperspectives-filter-hide' );
            }
        } );
    } // End filterTree()


    /**
     * Toggle the empty message when there is nothing in the tree.
     */
    function updateEmpty() {
        var empty = app.find( '.siteperspectives-root li.siteperspectives-node' ).length === 0;

        app.find( '.siteperspectives-empty' ).prop( 'hidden', ! empty );
    } // End updateEmpty()


    /**
     * Display options: saved and re-rendered on change.
     */
    function bindOptions() {
        var panel = $( '#siteperspectives-options' );

        panel.on( 'change input', 'input', function () {
            clearTimeout( optionsTimer );
            optionsTimer = setTimeout( function () {
                refreshTree( true );
            }, 450 );
        } );

        panel.on( 'click', '#siteperspectives-add-meta', function () {
            var template = document.getElementById( 'siteperspectives-meta-template' );

            if ( template ) {
                panel.find( '.siteperspectives-meta-rows' ).append( template.content.cloneNode( true ) );
                panel.find( '.siteperspectives-meta-key' ).last().trigger( 'focus' );
            }
        } );

        panel.on( 'click', '.siteperspectives-meta-remove', function () {
            $( this ).closest( '.siteperspectives-meta-row' ).remove();
            clearTimeout( optionsTimer );
            optionsTimer = setTimeout( function () {
                refreshTree( true );
            }, 450 );
        } );

        panel.on( 'focus', '.siteperspectives-meta-key', function () {
            if ( metaKeysLoaded ) {
                return;
            }

            metaKeysLoaded = true;

            request( 'siteperspectives_meta_keys', {} ).done( function ( response ) {
                if ( response.success ) {
                    var list = $( '#siteperspectives-meta-keys' ).empty();

                    $.each( response.data, function ( index, key ) {
                        list.append( $( '<option>' ).attr( 'value', key ) );
                    } );
                }
            } );
        } );
    } // End bindOptions()


    /**
     * Read the view options from the panel.
     *
     * @return {Object}
     */
    function gatherView() {
        var view = { collapsed: [], expanded: [], meta: [] };

        $( '#siteperspectives-options .siteperspectives-options-col[data-group]' ).each( function () {
            var group = $( this ).data( 'group' );

            $( this ).find( 'input:checked' ).each( function () {
                view[ group ].push( this.value );
            } );
        } );

        $( '#siteperspectives-options .siteperspectives-meta-row' ).each( function () {
            var row = $( this );
            var key = $.trim( row.find( '.siteperspectives-meta-key' ).val() );

            if ( key ) {
                view.meta.push( {
                    key: key,
                    label: $.trim( row.find( '.siteperspectives-meta-label' ).val() ),
                    collapsed: row.find( '.siteperspectives-meta-collapsed' ).is( ':checked' ) ? 1 : 0,
                    expanded: row.find( '.siteperspectives-meta-expanded' ).is( ':checked' ) ? 1 : 0
                } );
            }
        } );

        return view;
    } // End gatherView()


    /**
     * Ask the server to re-render the current (possibly unsaved) tree with the current view options.
     *
     * @param {boolean} saveView Whether to persist the view options.
     * @param {Object}  [treeOverride] Tree payload to use instead of the DOM.
     */
    function refreshTree( saveView, treeOverride ) {
        var open = {};

        app.find( '.siteperspectives-toggle[aria-expanded="true"]' ).each( function () {
            open[ $( this ).closest( 'li.siteperspectives-node' ).data( 'id' ) ] = true;
        } );

        app.addClass( 'siteperspectives-loading' );

        request( 'siteperspectives_render_tree', {
            tree: JSON.stringify( treeOverride || gatherTree() ),
            view: JSON.stringify( gatherView() ),
            save_view: saveView ? 1 : 0
        } ).done( function ( response ) {
            if ( ! response.success ) {
                return;
            }

            $.each( response.data, function ( name, html ) {
                app.find( '.siteperspectives-list[data-list="' + name + '"]' ).html( html );
            } );

            app.find( 'li.siteperspectives-node' ).each( function () {
                var node = $( this );

                if ( open[ node.data( 'id' ) ] ) {
                    toggleCard( node.children( '.siteperspectives-card' ), true );
                }
            } );

            if ( editing ) {
                initSortable();
            }

            filterTree( $.trim( $( '#siteperspectives-search' ).val() ).toLowerCase() );
            updateEmpty();
        } ).always( function () {
            app.removeClass( 'siteperspectives-loading' );
        } );
    } // End refreshTree()


    /**
     * Pick white text on dark and mid-tone backgrounds (relative luminance up to 0.22), black otherwise.
     *
     * @param {string} hex
     * @return {string}
     */
    function textColor( hex ) {
        var value = String( hex ).replace( '#', '' );

        if ( 3 === value.length ) {
            value = value.replace( /(.)/g, '$1$1' );
        }

        var luminance = 0;
        var weights = [ 0.2126, 0.7152, 0.0722 ];

        for ( var i = 0; i < 3; i++ ) {
            var channel = parseInt( value.substr( i * 2, 2 ), 16 ) / 255;

            luminance += weights[ i ] * ( channel <= 0.03928 ? channel / 12.92 : Math.pow( ( channel + 0.055 ) / 1.055, 2.4 ) );
        }

        return luminance <= 0.22 ? '#ffffff' : '#000000';
    } // End textColor()


    /**
     * Serialize one list into node objects.
     *
     * @param {jQuery} list
     * @return {Array}
     */
    function serializeList( list ) {
        var nodes = [];

        list.children( 'li.siteperspectives-node' ).each( function () {
            var node = $( this );
            var card = node.children( '.siteperspectives-card' );
            var type = node.data( 'type' );
            var title = $.trim( card.find( '.siteperspectives-in-title' ).first().val() || '' );
            var color = card.find( '.siteperspectives-in-color' ).first();

            if ( ( 'post' === type || 'admin' === type ) && title === String( node.data( 'orig' ) ) ) {
                title = '';
            }

            nodes.push( {
                id: node.data( 'id' ),
                type: type,
                post_id: node.data( 'post-id' ) || 0,
                title: title,
                url: card.find( '.siteperspectives-in-url' ).first().val() || '',
                icon: card.find( '.siteperspectives-in-icon' ).first().val() || '',
                color: ( color.length && '1' === String( color.attr( 'data-set' ) ) ) ? color.val() : '',
                hidden: card.find( '.siteperspectives-in-hidden' ).first().is( ':checked' ) ? 1 : 0,
                hide_public: card.find( '.siteperspectives-in-hide-public' ).first().is( ':checked' ) ? 1 : 0,
                children: serializeList( node.children( 'ul.siteperspectives-children' ) )
            } );
        } );

        return nodes;
    } // End serializeList()


    /**
     * Serialize all lists on the page.
     *
     * @return {Object}
     */
    function gatherTree() {
        var tree = {};

        app.find( '.siteperspectives-list.siteperspectives-root[data-list]' ).each( function () {
            tree[ $( this ).data( 'list' ) ] = serializeList( $( this ) );
        } );

        var home = $( '.siteperspectives-home-input' );

        if ( home.length ) {
            tree.home = $.trim( home.val() );
        }

        return tree;
    } // End gatherTree()


    /**
     * Edit mode: enter, leave, save, cancel and per-node controls.
     */
    function bindEditControls() {
        if ( ! config.canEdit ) {
            $( '#siteperspectives-edit' ).remove();
            return;
        }

        $( '#siteperspectives-edit' ).on( 'click', enterEdit );
        $( '#siteperspectives-save' ).on( 'click', saveTree );
        $( '#siteperspectives-cancel' ).on( 'click', cancelEdit );

        $( document ).on( 'keydown', function ( event ) {
            if ( editing && ( event.ctrlKey || event.metaKey ) && 's' === event.key.toLowerCase() ) {
                event.preventDefault();
                saveTree();
            }
        } );

        $( window ).on( 'beforeunload', function () {
            if ( editing && dirty ) {
                return config.i18n.confirmLeave;
            }
        } );

        app.on( 'paste', '.siteperspectives-new-title', function ( event ) {
            var data = event.originalEvent.clipboardData;

            if ( ! data ) {
                return;
            }

            event.preventDefault();
            this.setRangeText( data.getData( 'text' ).trim(), this.selectionStart, this.selectionEnd, 'end' );
            $( this ).trigger( 'input' );
        } );

        app.on( 'input', '.siteperspectives-in-title', function () {
            var node = $( this ).closest( 'li.siteperspectives-node' );

            node.children( '.siteperspectives-card' ).find( '.siteperspectives-title' ).first().text( $( this ).val() );
            setDirty();
        } );

        $( document ).on( 'input', '.siteperspectives-home-input', function () {
            $( '.siteperspectives-home-title' ).text( $( this ).val() );
            setDirty();
        } );

        app.on( 'input', '.siteperspectives-in-url', function () {
            var card = $( this ).closest( '.siteperspectives-card' );
            var value = $( this ).val();

            card.find( '.siteperspectives-inline-url-input' ).val( value );
            card.find( '.siteperspectives-inline-url a' ).attr( 'href', value );
            setDirty();
        } );

        app.on( 'input', '.siteperspectives-in-color', function () {
            var input = $( this );

            input.attr( 'data-set', '1' );
            var item = input.closest( 'li.siteperspectives-node' )[ 0 ];

            item.style.setProperty( '--siteperspectives-node-color', input.val(), 'important' );
            item.style.setProperty( '--siteperspectives-node-text', textColor( input.val() ), 'important' );
            setDirty();
        } );

        app.on( 'change', '.siteperspectives-in-hide-public', function () {
            $( this ).closest( 'li.siteperspectives-node' ).toggleClass( 'siteperspectives-is-hide-public', $( this ).is( ':checked' ) );
        } );

        app.on( 'change', '.siteperspectives-in-hidden', function () {
            $( this ).closest( 'li.siteperspectives-node' ).toggleClass( 'siteperspectives-hidden', $( this ).is( ':checked' ) );
            setDirty();
        } );

        app.on( 'click', '.siteperspectives-remove', function () {
            var node = $( this ).closest( 'li.siteperspectives-node' );
            var children = node.children( 'ul.siteperspectives-children' ).children( 'li.siteperspectives-node' );

            children.insertAfter( node );
            node.remove();
            initSortable();
            setDirty();
            updateEmpty();
        } );

        $( '#siteperspectives-reset' ).on( 'click', function () {
            if ( window.confirm( config.i18n.resetConfirm || 'Reset?' ) ) {
                request( 'siteperspectives_save_tree', { tree: JSON.stringify( { main: [] } ) } ).done( function () {
                    refreshTree( false, { main: [] } );
                    setDirty( false );
                } );
            }
        } );
    } // End bindEditControls()


    /**
     * Initialize sortable on every list that is not already sortable.
     */
    function initSortable() {
        app.find( '.siteperspectives-list' ).each( function () {
            var list = $( this );

            if ( ! list.hasClass( 'ui-sortable' ) ) {
                list.sortable( sortableOptions );
            }
        } );
    } // End initSortable()


    /**
     * Remove sortable from every list.
     */
    function destroySortable() {
        app.find( '.siteperspectives-list.ui-sortable' ).sortable( 'destroy' );
    } // End destroySortable()


    /**
     * Enter edit mode.
     */
    function enterEdit() {
        snapshot = {};

        app.find( '.siteperspectives-list.siteperspectives-root[data-list]' ).each( function () {
            snapshot[ $( this ).data( 'list' ) ] = $( this ).html();
        } );

        snapshot.home = $( '.siteperspectives-home-input' ).val();
        editing = true;
        app.addClass( 'siteperspectives-editing' );
        $( '#siteperspectives-add-panel' ).prop( 'hidden', false );
        $( '#siteperspectives-edit' ).hide();
        $( '.siteperspectives-edit-actions' ).prop( 'hidden', false );
        $( '#siteperspectives-add-parent' ).val( '' );
        refreshParentOptions();
        initSortable();
    } // End enterEdit()


    /**
     * Leave edit mode without touching the DOM contents.
     */
    function exitEdit() {
        editing = false;
        destroySortable();
        app.removeClass( 'siteperspectives-editing' );
        $( '#siteperspectives-add-panel' ).prop( 'hidden', true );
        $( '#siteperspectives-edit' ).show();
        $( '.siteperspectives-edit-actions' ).prop( 'hidden', true );
        setDirty( false );
    } // End exitEdit()


    /**
     * Cancel edit mode and restore the markup from when editing started.
     */
    function cancelEdit() {
        if ( dirty && ! window.confirm( config.i18n.confirmLeave ) ) {
            return;
        }

        destroySortable();

        $.each( snapshot, function ( name, html ) {
            if ( 'home' === name ) {
                $( '.siteperspectives-home-input' ).val( html );
                $( '.siteperspectives-home-title' ).text( html );
                return;
            }

            app.find( '.siteperspectives-list.siteperspectives-root[data-list="' + name + '"]' ).html( html );
        } );

        exitEdit();
        updateEmpty();
    } // End cancelEdit()


    /**
     * Track unsaved changes.
     *
     * @param {boolean} [value]
     */
    function setDirty( value ) {
        dirty = ( undefined === value ) ? true : value;
        $( '#siteperspectives-save-reminder' ).toggle( dirty && editing );
    } // End setDirty()


    /**
     * Show a status message next to the buttons.
     *
     * @param {string} message
     * @param {boolean} success
     */
    function showStatus( message, success ) {
        var status = $( '#siteperspectives-save-status' );

        status.text( message ).removeClass( 'is-success is-error' ).addClass( success ? 'is-success' : 'is-error' );

        setTimeout( function () {
            status.text( '' );
        }, 3500 );
    } // End showStatus()


    /**
     * Save the tree over AJAX.
     */
    function saveTree() {
        var button = $( '#siteperspectives-save' );
        var label = button.html();

        if ( button.prop( 'disabled' ) ) {
            return;
        }

        button.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + config.i18n.saving + '...' );

        request( 'siteperspectives_save_tree', { tree: JSON.stringify( gatherTree() ) } ).done( function ( response ) {
            if ( response.success ) {
                refreshColors( response.data.cssUrl );
                exitEdit();
                showStatus( config.i18n.saved, true );
            } else {
                showStatus( response.data || config.i18n.error, false );
            }
        } ).fail( function () {
            showStatus( config.i18n.error, false );
        } ).always( function () {
            button.prop( 'disabled', false ).html( label );
        } );
    } // End saveTree()


    /**
     * Load the refreshed color stylesheet so saved item colors keep showing after a re-render.
     *
     * @param {string} url
     */
    function refreshColors( url ) {
        var current = $( '#siteperspectives-dynamic-css' );

        if ( ! url || ! current.length ) {
            return;
        }

        $( '<link rel="stylesheet">' ).attr( 'href', url ).on( 'load', function () {
            current.remove();
            $( this ).attr( 'id', 'siteperspectives-dynamic-css' );
        } ).insertAfter( current );
    } // End refreshColors()


    /**
     * The "Add Items" panel.
     */
    function bindAddPanel() {
        var panel = $( '#siteperspectives-add-panel' );

        if ( ! panel.length ) {
            return;
        }

        panel.find( '.siteperspectives-acc-posts' ).each( function () {
            this.addEventListener( 'toggle', function () {
                var acc = $( this );

                if ( acc.prop( 'open' ) && ! acc.data( 'loaded' ) ) {
                    acc.data( 'loaded', true );
                    loadPosts( acc, true );
                }
            } );
        } );

        panel.on( 'input', '.siteperspectives-acc-search', function () {
            var acc = $( this ).closest( '.siteperspectives-acc-posts' );

            clearTimeout( acc.data( 'timer' ) );
            acc.data( 'timer', setTimeout( function () {
                loadPosts( acc, true );
            }, 300 ) );
        } );

        panel.on( 'focus mousedown', '#siteperspectives-add-parent', refreshParentOptions );

        panel.on( 'click', '.siteperspectives-acc-more', function () {
            loadPosts( $( this ).closest( '.siteperspectives-acc-posts' ), false );
        } );

        panel.on( 'click', '.siteperspectives-acc-add', function () {
            var acc = $( this ).closest( '.siteperspectives-acc-posts' );
            var items = [];

            acc.find( '.siteperspectives-acc-list input:checked' ).each( function () {
                items.push( { kind: 'post', id: this.value, sort: $( this ).closest( 'li' ).find( '.siteperspectives-acc-title' ).text() } );
            } );

            items.sort( function ( a, b ) {
                return a.sort.localeCompare( b.sort, undefined, { numeric: true, sensitivity: 'base' } );
            } );

            if ( items.length ) {
                addItems( items, 'main', function () {
                    acc.find( '.siteperspectives-acc-list input:checked' ).prop( 'checked', false );
                }, $( this ) );
            }
        } );

        panel.on( 'click', '.siteperspectives-add-custom', function () {
            var box = $( this ).closest( '#siteperspectives-acc-custom' );
            var title = $.trim( box.find( '.siteperspectives-new-title' ).val() );
            var url = $.trim( box.find( '.siteperspectives-new-url' ).val() );

            if ( ! title || ! url ) {
                showStatus( config.i18n.urlRequired, false );
                return;
            }

            addItems( [ { kind: 'custom', title: title, url: url } ], 'main', function () {
                box.find( 'input[type="text"]' ).val( '' );
            }, $( this ) );
        } );
    } // End bindAddPanel()


    /**
     * Load a page of posts into an accordion.
     *
     * @param {jQuery} acc
     * @param {boolean} reset
     */
    function loadPosts( acc, reset ) {
        var list = acc.find( '.siteperspectives-acc-list' );
        var more = acc.find( '.siteperspectives-acc-more' );
        var paged = reset ? 1 : ( acc.data( 'paged' ) || 1 ) + 1;

        request( 'siteperspectives_search_posts', {
            post_type: acc.data( 'post-type' ),
            search: acc.find( '.siteperspectives-acc-search' ).val(),
            paged: paged
        } ).done( function ( response ) {
            if ( ! response.success ) {
                return;
            }

            if ( reset ) {
                list.empty();
            }

            $.each( response.data.items, function ( index, item ) {
                var row = $( '<li><label><input type="checkbox"><span class="siteperspectives-acc-title"></span><span class="siteperspectives-status"></span></label></li>' );

                row.find( 'input' ).val( item.id );
                row.find( '.siteperspectives-acc-title' ).text( item.title );
                row.find( '.siteperspectives-status' ).text( item.status );
                list.append( row );
            } );

            if ( reset && ! response.data.items.length ) {
                list.append( $( '<li></li>' ).text( config.i18n.noResults ) );
            }

            acc.data( 'paged', paged );
            more.prop( 'hidden', ! response.data.hasMore );
        } );
    } // End loadPosts()


    /**
     * Ask the server for markup for new nodes and append it to a list.
     *
     * @param {Array}    items
     * @param {string}   target
     * @param {Function} done
     */
    function addItems( items, target, done, button ) {
        var parentId = $( '#siteperspectives-add-parent' ).val() || '';
        var label = button ? button.html() : '';

        if ( button ) {
            if ( button.prop( 'disabled' ) ) {
                return;
            }

            button.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + config.i18n.adding );
        }

        request( 'siteperspectives_add_items', { items: JSON.stringify( items ) } ).done( function ( response ) {
            if ( ! response.success || ! response.data.html ) {
                showStatus( config.i18n.error, false );
                return;
            }

            var list = app.find( '.siteperspectives-list.siteperspectives-root[data-list="' + target + '"]' );

            if ( parentId ) {
                var parentNode = app.find( 'li.siteperspectives-node' ).filter( function () {
                    return $( this ).attr( 'data-id' ) === parentId;
                } ).first();

                if ( parentNode.length ) {
                    list = parentNode.children( '.siteperspectives-children' );
                }
            }

            var added = $( $.parseHTML( $.trim( response.data.html ) ) ).filter( 'li' );

            list.append( added );
            added.addClass( 'siteperspectives-flash' );
            initSortable();
            setDirty();
            updateEmpty();
            showStatus( config.i18n.added, true );

            if ( added.length ) {
                added.first()[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
            }

            refreshParentOptions();

            if ( done ) {
                done();
            }
        } ).fail( function () {
            showStatus( config.i18n.error, false );
        } ).always( function () {
            if ( button ) {
                button.prop( 'disabled', false ).html( label );
            }
        } );
    } // End addItems()


    /**
     * Collect every item in a list, with its depth, for the "Add under" dropdown.
     *
     * @param {jQuery} list
     * @param {number} depth
     * @param {Array} out
     */
    function collectParents( list, depth, out ) {
        list.children( 'li.siteperspectives-node' ).each( function () {
            var node = $( this );
            var title = $.trim( node.children( '.siteperspectives-card' ).find( '.siteperspectives-title' ).first().text() );

            out.push( { id: node.attr( 'data-id' ), label: new Array( depth + 1 ).join( '\u2013 ' ) + ( title || config.i18n.untitled ) } );

            if ( depth < 8 ) {
                collectParents( node.children( '.siteperspectives-children' ), depth + 1, out );
            }
        } );
    } // End collectParents()


    /**
     * Rebuild the "Add under" dropdown from the tree as it is right now, keeping the current choice when it still exists.
     */
    function refreshParentOptions() {
        var select = $( '#siteperspectives-add-parent' );

        if ( ! select.length ) {
            return;
        }

        var current = select.val() || '';
        var groups = [ { list: 'main', label: config.i18n.parentMain }, { list: 'utility', label: config.i18n.parentUtility } ];

        select.children( ':not(:first-child)' ).remove();

        $.each( groups, function ( index, group ) {
            var items = [];

            collectParents( app.find( '.siteperspectives-list.siteperspectives-root[data-list="' + group.list + '"]' ), 0, items );

            if ( ! items.length ) {
                return;
            }

            var optgroup = $( '<optgroup>' ).attr( 'label', group.label );

            $.each( items, function ( i, item ) {
                optgroup.append( $( '<option>' ).val( item.id ).text( item.label ) );
            } );

            select.append( optgroup );
        } );

        select.val( current );

        if ( select.val() !== current ) {
            select.val( '' );
        }
    } // End refreshParentOptions()


    /**
     * Dashicon picker popover.
     */
    function bindIconPicker() {
        $( document ).on( 'click', '.siteperspectives-icon-pick', function ( event ) {
            event.stopPropagation();
            openIconPicker( $( this ) );
        } );

        $( document ).on( 'click', function ( event ) {
            if ( iconTarget && ! $( event.target ).closest( '.siteperspectives-icon-popover' ).length ) {
                closeIconPicker();
            }
        } );

        $( document ).on( 'keydown', function ( event ) {
            if ( iconTarget && 'Escape' === event.key ) {
                closeIconPicker();
            }
        } );
    } // End bindIconPicker()


    /**
     * Build the icon grid markup once.
     *
     * @return {string}
     */
    function getIconGrid() {
        if ( ! iconGridHtml ) {
            $.each( config.icons || [], function ( index, slug ) {
                iconGridHtml += '<button type="button" data-icon="' + slug + '" title="' + slug + '"><span class="dashicons dashicons-' + slug + '"></span></button>';
            } );
        }

        return iconGridHtml;
    } // End getIconGrid()


    /**
     * Open the picker below a button.
     *
     * @param {jQuery} button
     */
    function openIconPicker( button ) {
        closeIconPicker();

        var input = button.siblings( 'input[type="hidden"]' ).first();
        var current = input.val();
        var offset = button.offset();
        var pop = $( '<div class="siteperspectives-icon-popover" role="dialog"></div>' );
        var search = $( '<input type="search" autocomplete="off">' ).attr( 'placeholder', config.i18n.searchIcons );
        var grid = $( '<div class="siteperspectives-icon-grid"></div>' ).html( getIconGrid() );
        var footer = $( '<div class="siteperspectives-icon-popover-footer"></div>' );
        var reset = $( '<button type="button" class="siteperspectives-button siteperspectives-button-secondary siteperspectives-button-small"></button>' ).text( config.i18n.resetIcon );

        grid.find( '[data-icon="' + current + '"]' ).addClass( 'is-selected' );
        footer.append( reset );
        pop.append( search, grid, footer );

        pop.css( {
            top: offset.top + button.outerHeight() + 4,
            left: Math.max( 8, Math.min( offset.left, $( window ).width() - 360 ) )
        } );

        $( 'body' ).append( pop );
        search.trigger( 'focus' );
        iconTarget = { button: button, input: input, pop: pop };

        search.on( 'input', function () {
            var value = $.trim( $( this ).val() ).toLowerCase();

            grid.children( 'button' ).each( function () {
                $( this ).toggle( '' === value || $( this ).data( 'icon' ).indexOf( value ) !== -1 );
            } );
        } );

        grid.on( 'click', 'button', function () {
            applyIcon( $( this ).data( 'icon' ) );
        } );

        reset.on( 'click', function () {
            applyIcon( '' );
        } );
    } // End openIconPicker()


    /**
     * Apply a chosen icon to the field and card.
     *
     * @param {string} slug Empty to use the default.
     */
    function applyIcon( slug ) {
        var shown = slug || 'category';

        iconTarget.input.val( slug ).trigger( 'change' );
        iconTarget.button.find( '.dashicons' ).attr( 'class', 'dashicons dashicons-' + shown );

        var node = iconTarget.button.closest( 'li.siteperspectives-node' );

        if ( node.length ) {
            node.children( '.siteperspectives-card' ).find( '.siteperspectives-node-icon' ).first().attr( 'class', 'siteperspectives-node-icon dashicons dashicons-' + shown );
            setDirty();
        }

        closeIconPicker();
    } // End applyIcon()


    /**
     * Close the picker.
     */
    function closeIconPicker() {
        if ( iconTarget ) {
            iconTarget.pop.remove();
            iconTarget = null;
        }
    } // End closeIconPicker()

} )( jQuery );
