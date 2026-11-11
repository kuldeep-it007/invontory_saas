<template>
	  <multiselect
	  	v-model="selected" 
	  	:options="options" 
	  	:searchable="true" 
	  	track-by="key"
	  	label="value"
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="false"
	  	@select="typeSelected"
	  	></multiselect>
</template>
<script>
import Multiselect from 'vue-multiselect'
import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
import Vue from 'vue'
import AppMixin from '../../vuex-mixin.js'
export default{
	mixins:[AppMixin],
	components:{
		Multiselect
	},
	props:{
		value:{
			type:[Object,null],
			required:false,
			default: function(){
				return null
			}
		}
	},
	
	data(){
		return {
			selected:null,
			options:[]
		}
	},
	mounted(){
		this.options = this.master.shipping_reserve
		if(this.value)
		{
			this.selected = this.value
		}
		else
		{
			this.selected = this.options[0]
			this.$emit('input',this.selected)
		}
	},
	methods:{
    	typeSelected(type){
    		this.$emit('input',type)
    	}
	},
	watch:{
		value(){
			this.selected = this.value
		}
	},
}
</script>