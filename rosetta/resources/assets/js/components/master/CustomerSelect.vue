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
		'company',
		'disabled',
		'value',
		'onlyCompany'
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
				return 'Select Distributor'
			if(this.customer_type.code == 'SUPER_STOCKIST')
				return 'Select Super Stockist'
			if(this.customer_type.code == 'SUPER_STOCKIST_DISTRIBUTOR')
				return 'Select Distributor'
			if(this.customer_type.code == 'SUPER_STOCKIST_DEALER')
				return 'Select Dealer'
			return 'Select Dealer'
		}
	},
	mounted(){
		if(this.company.id && this.customer_type)
			this.loadCustomers()
	},
	methods:{
		remove(){
			this.$emit('input',null)
		},
		loadCustomers(){
			let self = this
			if(!this.company)
				return
			self.selected = null
			let url = ""
			if(this.customer_type.code == 'DISTRIBUTO')
			{
				url = `companies/${this.company.id}/distributorSearch`
			}
			if(this.customer_type.code == 'SUPER_STOCKIST')
			{
				url = `companies/${this.company.id}/superStockistSearch`
			}
			if(this.customer_type.code == 'DEALER')
			{
				url = `companies/${this.company.id}/dealerSearch`
				if(this.onlyCompany)
				{
					url = url + '?onlyCompany'
				}
			}

			if(this.customer_type.code == 'SUPER_STOCKIST_DISTRIBUTOR')
			{
				url = `companies/${this.company.id}/distributorSearch?isDistrubutorUnderSs=true`
			}

			if(this.customer_type.code == 'SUPER_STOCKIST_DEALER')
			{
				url = `companies/${this.company.id}/dealerSearch?isDealerUnderDistUnderSs=true`
			}

			this.loading = true
			Vue.axios.get(url)
			.then(response => {
				this.loading = false
				self.options = response.data
				if(this.value)
				{
					this.selected = this.value
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
		'company.id':function(){
			if(!this.company)
			{
				this.options = []
				this.selected = null
				this.$emit('input',null)
				return
			}
			this.loadCustomers()
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