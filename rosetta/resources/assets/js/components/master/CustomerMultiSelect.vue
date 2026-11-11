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
		'multiple',
	],
	data(){
		return {
			selected:null,
			options:[]
		}
	},
	computed:{
		placeholder(){
			if(!this.customer_type)
				return "Select Customer"
			if(this.customer_type.code == 'DISTRIBUTO')
				return 'Select distributor'
			return 'Select Dealer'
		}
	},
	mounted(){
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
			self.options = []
			let url = ""
			if(this.customer_type.code == 'DISTRIBUTO')
			{
				url = `companies/${this.company.id}/distributors`
			}
			if(this.customer_type.code == 'DEALER')
			{
				url = `companies/${this.company.id}/dealers`
			}

			Vue.axios.get(url)
			.then(response => {
				self.options = response.data
			})
		},
    	typeSelected(customer){
    		this.$emit('input',customer)
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