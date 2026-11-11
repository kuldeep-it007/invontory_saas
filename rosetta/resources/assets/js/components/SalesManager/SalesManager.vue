<template>
	  <multiselect
	  	v-model="selected" 
	  	:options="options" 
	  	:searchable="true" 
	  	track-by="id"
	  	label="name"
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="false"
	  	@select="typeSelected"
	  	@close="closed"
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
		company:{
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
		this.loadSalesManagers()
	},
	methods:{
		closed(){
			if(!this.selected)
				this.$emit('blur',null)
		},
		loadSalesManagers(){
			let self = this
			Vue.axios.get(`companies/${this.company.id}/sales-managers`)
			.then(response => {
				self.options = response.data
				if(this.value)
				{
					self.selected = _.findWhere(self.options,{id:self.value.id})
				}
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
		'company.id':function(){
			this.loadSalesManagers()
		}
	},
}
</script>