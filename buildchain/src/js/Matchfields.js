/** global: Craft */
/** global: Garnish */
/**
 * Match field index class
 */
Craft.Cockpit.MatchFieldIndex = Craft.BaseElementIndex.extend({
    editableMatchFieldTypes: null,
    $newMatchFieldBtnGroup: null,
    $newMatchFieldBtn: null,

    init: function (elementType, $container, settings) {
        this.on('selectSource', $.proxy(this, 'updateButton'));
        this.on('selectSite', $.proxy(this, 'updateButton'));
        this.base(elementType, $container, settings);
    },

    afterInit: function() {
        // Find which of the visible matchFieldTypes the user has permission to create new match fields in
        this.editableMatchFieldTypes = [];

        for (const matchFieldType of Craft.Cockpit.editableMatchFieldTypes) {
            if (this.getSourceByKey(`matchFieldType:${matchFieldType.uid}`)) {
                this.editableMatchFieldTypes.push(matchFieldType);
            }
        }

        this.base();
    },

    getDefaultSourceKey: function() {
        // Did they request a specific Match Field matchFieldType in the URL?
        if (
            this.settings.context === 'index' &&
            typeof defaultMatchFieldTypeHandle !== 'undefined'
        ) {
            for (var i = 0; i < this.$sources.length; i++) {
                var $source = $(this.$sources[i]);

                if ($source.data('handle') == defaultMatchFieldTypeHandle) {
                    return $source.data('key');
                }
            }
        }
    },

    updateButton: function() {
        if (!this.$source) {
            return;
        }

        // Get the handle of the selected source
        const matchFieldTypeHandle = this.$source.data('handle');

        // Update the New Match Field button
        // ---------------------------------------------------------------------

        if (this.editableMatchFieldTypes.length) {
            // Remove the old button, if there is one
            if (this.$newMatchFieldBtnGroup) {
                this.$newMatchFieldBtnGroup.remove();
            }

            // Determine if they are viewing a matchFieldType that they have permission to create match fields in
            const selectedMatchFieldType = this.editableMatchFieldTypes.find(
                (t) => t.handle === matchFieldTypeHandle
            );

            this.$newMatchFieldBtnGroup = $(
                '<div class="btngroup submit" data-wrapper/>'
            );
            let $menuBtn;
            const menuId = `new-match-field-menu-${Craft.randomString(10)}`;

            // If they are, show a primary "New product" button, and a dropdown of the other matchFieldTypes (if any).
            // Otherwise only show a menu button
            if (selectedMatchFieldType) {
                const visibleLabel =
                    this.settings.context === 'index'
                        ? Craft.t('cockpit', 'New match field')
                        : Craft.t('cockpit', 'New {matchFieldType} field', {
                            matchFieldType: selectedMatchFieldType.name,
                        });

                const ariaLabel =
                    this.settings.context === 'index'
                        ? Craft.t('cockpit', 'New {matchFieldType} field', {
                            matchFieldType: selectedMatchFieldType.name,
                        })
                        : visibleLabel;

                // In index contexts, the button functions as a link
                // In non-index contexts, the button triggers a slideout editor
                const role = this.settings.context === 'index' ? 'link' : null;

                this.$newMatchFieldBtn = Craft.ui
                    .createButton({
                        label: visibleLabel,
                        ariaLabel: ariaLabel,
                        spinner: true,
                        role: role,
                    })
                    .addClass('submit add icon')
                    .appendTo(this.$newMatchFieldBtnGroup);

                this.addListener(this.$newMatchFieldBtn, 'click mousedown', (event) => {
                    // If this is the element index, check for Ctrl+clicks and middle button clicks
                    if (
                        this.settings.context === 'index' &&
                        ((event.type === 'click' && Garnish.isCtrlKeyPressed(event)) ||
                            (event.type === 'mousedown' && event.originalEvent.button === 1))
                    ) {
                        window.open(
                            Craft.getUrl(`cockpit/matchfields/${matchFieldType.handle}/new`)
                        );
                    } else if (event.type === 'click') {
                        this._createMatchFieldEntry(selectedMatchFieldType);
                    }
                });

                if (this.editableMatchFieldTypes.length > 1) {
                    $menuBtn = $('<button/>', {
                        type: 'button',
                        class: 'btn submit menubtn btngroup-btn-last',
                        'aria-controls': menuId,
                        'data-disclosure-trigger': '',
                        'aria-label': Craft.t('cockpit', 'New match field, choose a type'),
                    }).appendTo(this.$newMatchFieldBtnGroup)
                }
            } else {
                this.$newMatchFieldBtn = $menuBtn = Craft.ui
                    .createButton({
                        label: Craft.t('cockpit', 'New match field'),
                        ariaLabel: Craft.t('cockpit', 'New match field, choose a type'),
                        spinner: true,
                    })
                    .addClass('submit add icon menubtn btngroup-btn-last')
                    .attr('aria-controls', menuId)
                    .attr('data-disclosure-trigger', '')
                    .appendTo(this.$newMatchFieldBtnGroup);
            }

            this.addButton(this.$newMatchFieldBtnGroup);

            if ($menuBtn) {
                const $menuContainer = $('<div/>', {
                    id: menuId,
                    class: 'menu menu--disclosure',
                }).appendTo(this.$newMatchFieldBtnGroup);

                const $ul = $('<ul/>').appendTo($menuContainer);

                for (const matchFieldType of this.editableMatchFieldTypes) {
                    const anchorRole = this.settings.context === 'index' ? 'link' : 'button';
                    if (
                        this.settings.context === 'index' ||
                        matchFieldType !== selectedMatchFieldType
                    ) {
                        const $li = $('<li/>').appendTo($ul);
                        const $a = $('<a/>', {
                            role: anchorRole === 'button' ? 'button' : null,
                            href: Craft.getUrl(`cockpit/matchfields/${matchFieldType.handle}/new`),
                            type: anchorRole === 'button' ? 'button' : null,
                            text: Craft.t('cockpit', 'New {matchFieldType} field', {
                                matchFieldType: matchFieldType.name,
                            }),
                        }).appendTo($li);

                        this.addListener($a, 'activate', () => {
                            $menuBtn.data('trigger').hide();
                            this._createMatchFieldEntry(matchFieldType.id);
                        });

                        if (anchorRole === 'button') {
                            this.addListener($a, 'keydown', (event) => {
                                if (event.keyCode === Garnish.SPACE_KEY) {
                                    event.preventDefault();
                                    $menuBtn.data('trigger').hide();
                                    this._createMatchFieldEntry(matchFieldType.id);
                                }
                            })
                        }
                    }
                }

                new Garnish.DisclosureMenu($menuBtn);
            }
        }

        // Update the URL if we're on the Match Field Type index
        if (this.settings.context === 'index') {
            let uri = 'cockpit/matchfields';

            if (matchFieldTypeHandle) {
                uri += '/' + matchFieldTypeHandle;
            }

            Craft.setPath(uri);
        }
    },

    _createMatchFieldEntry: function (matchFieldTypeId) {
        if (this.$newMatchFieldBtn.hasClass('loading')) {
            console.warn('New match field creation already in progress.');
            return;
        }

        // Find the match field type
        const matchFieldType = this.editableMatchFieldTypes.find(
            (t) => t.id === matchFieldTypeId
        );

        if (!matchFieldType) {
            throw `Invalid match field type ID: ${matchFieldTypeId}`;
        }

        this.$newMatchFieldBtn.addClass('loading');

        Craft.sendActionRequest('POST', 'cockpit/match-fields/create', {
            data: {
                siteId: this.siteId,
                matchFieldType: matchFieldType.handle,
            },
        })
            .then(({data}) => {
                if (this.settings.context === 'index') {
                    document.location.href = Craft.getUrl(data.cpEditUrl, {fresh: 1});
                } else {
                    const slideout = Craft.createElementEditor(this.elementType, {
                        siteId: this.siteId,
                        elementId: data.matchfield.id,
                        draftId: data.matchfield.draftId,
                        params: {
                            fresh: 1
                        }
                    });
                    slideout.on('submit', () => {
                        this.clearSearch();
                        this.setSelectedSortAttribute('dateCreated', 'desc');
                        this.selectElementAfterUpdate(data.matchfield.id);
                        this.updateElements();
                    })
                }
            })
            .finally(() => {
                this.$newMatchFieldBtn.removeClass('loading');
            })
    },
})
