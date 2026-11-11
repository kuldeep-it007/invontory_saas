<template>
	  <multiselect
	  	v-model="selected" 
	  	:options="options" 
	  	:searchable="true" 
	  	track-by="id"
	  	label="name"
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="true"
	  	@select="typeSelected"
	  	@remove="remove"
	  	placeholder="Select Company"
	  	:disabled="disable"
	  	:loading="loading"
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
	props:[
		'value',
		'region',
		'disabled'
	],
	data(){
		return {
			selected:null,
			options:[
                {
                    "id": "PD",
                    "name" : "PD"
                },
                {
                    "id": "MD",
                    "name" : "MD"
                }
            ],
			disable:false,
			loading:false
		}
	},
	mounted(){
		if(this.disabled)
			this.disable = this.disabled
		//this.loadTenants()
	},
	methods:{
		remove(){
			this.$emit('input',null)
		},
		loadTenants(){
			let self = this
			if(!this.region)
				return
			self.selected = null
			this.loading = true
			Vue.axios.get(`regions/${this.region.id}/companies`)
			.then(response => {
				this.loading = false
				self.options = response.data
				if(this.value)
				{
					self.selected = this.value
				}
				else
				{
					self.selected = self.options[0]
					this.$emit('input',self.selected)
				}
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
		'region.id':function(){
			this.loadCompanies()
		},
		disabled(){
			this.disable = this.disabled
		}
	},
}
</script>