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
	  	@input="typeSelected"
	  	:disabled="disabled"
	  	:placeholder="placeholder"
	  	:multiple="multiple"
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
			selected:[],
			options:[]
		}
	},
	computed:{
		placeholder(){
			if(this.customer_type.code == 'SUPER_STOCKIST')
				return 'Select Super Stockist'
		}
	},
	mounted(){
		this.loadCustomers()
	},
	methods:{
		remove(){
			this.$emit('input',this.selected)

		},
		loadCustomers(){
			let self = this
			if(!this.company)
				return
			self.selected = null
			self.options = []
			let url = ""
			if(this.customer_type.code == 'SUPER_STOCKIST')
			{
				url = `companies/${this.company.id}/super_stockists`
			}

			Vue.axios.get(url)
			.then(response => {
				self.options = response.data
			})
		},
    	typeSelected(customer){
    		this.$emit('input',this.selected)
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