<template>
	  <multiselect
	  	v-model="selected" 
	  	:options="options" 
	  	:searchable="true" 
	  	placeholder="Select type"
	  	track-by="key"
	  	label="value"
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="true"
	  	@select="typeSelected"
	  	@remove="typeRemoved"
	  	:disabled="disabled"
	  	></multiselect>
</template>
<script>
import Multiselect from 'vue-multiselect'
import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
import Vue from 'vue'
import AppMixin from '../../../vuex-mixin.js'
import CustomerTypes from './CustomerTypes'
export default{
	mixins:[AppMixin],
	components:{
		Multiselect
	},
	props:{
		value:{
			type:Object,
			required:false
		},
		disabled:{
			type:Boolean,
			required:false,
			default:false
		}//#780
	},
	
	data(){
		return {
			selected:null,
			options: CustomerTypes
		}
	},
	mounted(){
		if(this.value)
		{
			this.selected = this.value
		}
	},
	methods:{
    	typeSelected(type){
    		//console.log(type)
    		this.$emit('input',type)
    	},
    	typeRemoved(){
    		this.$emit('input',null)
    	}
	},
	watch:{
		value(){
			this.selected = this.value
		}
	},
}
</script>