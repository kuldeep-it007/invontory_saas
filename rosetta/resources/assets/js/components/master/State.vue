<template>
	  <multiselect
	  	v-model="selected" 
	  	:options="options" 
	  	:searchable="true" 
	  	track-by="code"
	  	label="name"
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="true"
	  	@select="typeSelected"
	  	@close="closed"
	  	placeholder="Select State"
	  	@remove="remove"
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
			type:Object,
			required:false,
			default: function(){
				return null
			}
		},
		country:{
			type:Object,
			required:true
		}
	},
	
	data(){
		return {
			selected:null,
			options:[]
		}
	},
	mounted(){
		this.loadStates()
	},
	methods:{
		remove(){
			this.selected = null
			this.$emit('input',null)
		},
		closed(){
			if(!this.selected)
				this.$emit('blur',null)
		},
		loadStates(){
			let self = this
			if(!this.country)
				return
			self.selected = null
			Vue.axios.get(`countries/${this.country.code}/states`)
			.then(response => {
				self.options = response.data
				//self.selected = self.options[0]
				//self.typeSelected(self.selected)
			})
		},
    	typeSelected(type){
    		this.$emit('input',type)
    	}
	},
	watch:{
		value(){
			this.selected = this.value
		},
		'country.code':function(){
			this.loadStates()
		}
	},
}
</script>