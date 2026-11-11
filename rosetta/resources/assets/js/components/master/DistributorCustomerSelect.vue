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
	  	:disabled="disabled"
	  	:placeholder="placeholder"
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
		'customer_type',
		'disabled',
		'value',
		'use_distributor',
		'type'
	],
	data(){
		return {
			selected:null,
			options:[],
			loading:false,
		}
	},
	computed:{
		placeholder(){
			if(!this.customer_type)
				return "Select Customer"
			if(this.customer_type.code == 'DISTRIBUTO')
				return 'Select co distributor'
			return 'Select Dealer'
		}
	},
	methods:{
		remove(){
			this.$emit('input',null)
		},
		loadCustomers(){
			let self = this
			self.selected = null
			let url = ""
			let t = 2
			if(this.customer_type.code == 'DISTRIBUTO')
			{
				if(this.type)
					t = this.type
				url = `companies/${this.use_distributor.company.id}/distributors/${this.use_distributor.id}/co_distributors?type=${t}`
			}
			if(this.customer_type.code == 'DEALER')
			{
				url = `companies/${this.use_distributor.company.id}/distributors/${this.use_distributor.id}/dealers`
			}
			this.loading = true
			Vue.axios.get(url)
			.then(response => {
				this.loading = false
				self.options = response.data
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
		'customer_type.code':function(){
			if(!this.customer_type)
			{
				this.options = []
				this.selected = null
				this.$emit('input',null)
				return
			}

			this.loadCustomers()
		}
	},
}
</script>