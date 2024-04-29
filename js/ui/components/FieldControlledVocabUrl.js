const template = pkp.Vue.compile(`
    <div
        class="pkpFormField pkpAutosuggest pkpFormField--url"
        :class="{
            'pkpAutosuggest--disabled': isDisabled,
            'pkpAutosuggest--inline': isLabelInline,
            'pkpAutosuggest--rtl': isRTL,
        }"
    >
        <div class="pkpFormField__heading" ref="heading">
            <form-field-label
                :controlId="controlId"
                :label="label"
                :localeLabel="localeLabel"
                :isRequired="isRequired"
                :requiredLabel="__('common.required')"
                :multilingualLabel="multilingualLabel"
            />
            <tooltip v-if="tooltip" aria-hidden="true" :tooltip="tooltip" label="" />
            <span
                v-if="tooltip"
                class="-screenReader"
                :id="describedByTooltipId"
                v-html="tooltip"
            />
            <help-button
                v-if="helpTopic"
                :id="describedByHelpId"
                :topic="helpTopic"
                :section="helpSection"
                :label="__('help.help')"
            />
        </div>
        <div
            v-if="isPrimaryLocale && description"
            class="pkpFormField__description"
            v-html="description"
            :id="describedByDescriptionId"
        />
        <div class="pkpFormField__control pkpAutosuggest__control">
            <div
                class="pkpAutosuggest__inputWrapper pkpFormField__input"
                :class="{
                    'pkpAutosuggest__inputWrapper--multilingual':
                        isMultilingual && locales.length > 1,
                    'pkpAutosuggest__inputWrapper--focus': isFocused,
                }"
                ref="values"
                :id="describedBySelectedId"
                @click="setFocusToInput"
            >
                <span class="-screenReader">{{ selectedLabel }}</span>
                <span v-if="!currentValue.length" class="-screenReader">
                    {{ __('common.none') }}
                </span>
                <pkp-badge
                    v-else
                    v-for="item in currentSelected"
                    :key="item.value"
                    class="pkpAutosuggest__selection"
                >
                    <a v-if="isValidUrl(item.label)" :href="item.label" target="_new">{{ item.label }}</a>
                    <span v-else>{{ item.label }}</span>
                    <button
                        v-if="!isDisabled"
                        class="pkpAutosuggest__deselect"
                        @click.stop.prevent="deselect(item)"
                    >
                        <icon icon="times" />
                        <span class="-screenReader">
                            {{ deselectLabel.replace('{$item}', item.label) }}
                        </span>
                    </button>
                </pkp-badge>
                <vue-autosuggest
                    v-if="!isDisabled"
                    v-model="inputValue"
                    ref="autosuggest"
                    class="pkpAutosuggest__autosuggester"
                    v-bind="autosuggestOptions"
                    @selected="selectSuggestion"
                    @focus="() => (isFocused = true)"
                    @blur="() => (isFocused = false)"
                />
            </div>
            <multilingual-progress
                v-if="isMultilingual && locales.length > 1"
                :id="multilingualProgressId"
                :count="multilingualFieldsCompleted"
                :total="locales.length"
            />
            <field-error
                v-if="errors && errors.length"
                :id="describedByErrorId"
                :messages="errors"
            />
        </div>
    </div>
`);

const FieldControlledVocab =
    pkp.controllers.Container.components.PkpForm.components.FormPage.components
        .FormGroup.components.FieldControlledVocab;

pkp.Vue.component('field-controlled-vocab-url', {
    name: 'FieldControlledVocabUrl',
    extends: FieldControlledVocab,
    methods: {
        isValidUrl: function (string) {
            try {
                new URL(string);
                return true;
            } catch (err) {
                return false;
            }
        },
        select(item) {
			if (!item) {
				if (!this.inputValue || !this.suggestions.length) {
					return;
				}
				item = this.suggestions[0];
			}
            if (this.isValidUrl(item.value)) {
                this.setSelected([...this.currentSelected, item]);
            } else {
                this.setErrors([this.__('validator.active_url')]);
            }
			this.inputValue = '';
			this.$nextTick(() => {
				this.$nextTick(() => {
					this.$nextTick(() =>
						this.$el.querySelector('#' + this.controlId).focus()
					);
				});
			});
		},
        setErrors: function(errors) {
			this.$emit('set-errors', this.name, errors, this.localeKey);
		},
    },
    render: function (h) {
        return template.render.call(this, h);
    },
});
