<template>
	  <multiselect
	  	v-model="selected" 
	  	:options="options" 
	  	:searchable="true" 
	  	track-by="id"
	  	label="title"
	  	placeholder="Select"
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
		Vue.axios.get('master/imageclasses')
		.then(response => {
			this.options = response.data
			this.selected = this.options[0]
			this.$emit('input',this.selected)
		})
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