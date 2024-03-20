<template>
    <multiselect 
        v-if="ajaxSearch"
        v-model="modelDataTmp" 
        @select="onSelect" 
        :allow-empty="inlineAllowEmpty" 
        :options="options" 
        :disabled="disabled" 
        :placeholder="inlinePlaceholder" 
        :selectLabel="inlineSelectLabel" 
        :deselectLabel="inlineDeselectLabel" 
        :track-by="inlineTrackBy" 
        :label="inlineLabel"

        @search-change="onSearhChange"
        :searchable="true" 
        :loading="isLoading" 
        :internal-search="false"
        :clear-on-select="false" 
        :close-on-select="true"
    >
        <span slot="noOptions">{{inlineNoOptions}}</span>
    </multiselect>

    <multiselect 
        v-else
        v-model="modelDataTmp" 
        @select="onSelect" 
        :allow-empty="inlineAllowEmpty" 
        :options="options" 
        :disabled="disabled" 
        :placeholder="inlinePlaceholder" 
        :selectLabel="inlineSelectLabel" 
        :deselectLabel="inlineDeselectLabel" 
        :track-by="inlineTrackBy" 
        :label="inlineLabel"
    >
        <span slot="noOptions">{{inlineNoOptions}}</span>
    </multiselect>
</template>
<style src="node_modules/vue-multiselect/dist/vue-multiselect.min.css"></style>
<style src="@/vendor/libs/vue-multiselect/vue-multiselect.scss" lang="scss"></style>
<script>
import Multiselect from "node_modules/vue-multiselect";
export default {
    components: {
        Multiselect,
    },
    props:[
        'ajaxSearch',
        'allow-empty',
        'label',
        'options',
        'placeholder',
        'selectLabel',
        'deselectLabel',
        'noOptions',
        'isLoading',
        'track-by',
        'modelData',
        'disabled'
    ],    
    data: () => ({
        modelDataTmp: {},
        inlineLabel: '',
        inlineSelectLabel: '',
        inlineDeselectLabel: '',
        inlineTrackBy: '',
        inlineAllowEmpty: '',
        inlinePlaceholder: '',
        inlineNoOptions: '',
    }),
    created(){
        this.inlineLabel = this.label==undefined?'text':this.label;
        this.inlineTrackBy = this.trackBy==undefined?'value':this.trackBy;
        this.inlineAllowEmpty = this.allowEmpty==undefined?false:this.allowEmpty;
        this.inlineSelectLabel = this.selectLabel==undefined?'Press enter to select':this.selectLabel;
        this.inlineDeselectLabel = this.deselectLabel==undefined?'Press enter to remove':this.deselectLabel;
        this.inlineNoOptions = this.noOptions==undefined?'List is empty':this.noOptions;
        this.inlinePlaceholder = this.placeholder==undefined?'Select':this.placeholder;
        // if(this.modelData>0)
        this.setSelected(this.modelData);
    },
    watch: {
        'modelData': function(v) {
            this.setSelected(this.modelData);
        },
        'options': function(v) {
            this.setSelected(this.modelData);
        }
    },
    methods: {
        onSelect(selectedOption){
            this.$emit('onSelect', selectedOption.value);
        },
        onSearhChange(query){
            this.$emit('onSearhChange', query);
        },
        setSelected(value) {     
            this.modelDataTmp = this.options.find(function( d ) {
                return d.value == value
            });
        }
    }
};
</script>