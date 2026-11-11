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
		'selectedSuperStockist',
		'company',
		'value',
		'withOrders',
	],
	data(){
		return {
			selected:null,
			options:[]
		}
	},
	computed:{
		placeholder(){
			return 'Select Distributor'
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
			self.selected = null
			let url = `companies/${self.company.id}/super_stockists/${self.selectedSuperStockist.id}/searchDistributors`
			if(this.withOrders)
			{
				url = url + '?withOrders'
			}//#750
			Vue.axios.get(url)
			.then(response => {
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
	},
}
</script>