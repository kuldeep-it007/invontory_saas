<template>
	<multiselect
	  	v-model="selected" 
	  	:options="taxes" 
	  	:searchable="false" 
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="true"
	  	track-by="code"
	  	label="name"
	  	:multiple="false"
	  	@input="taxSelected"
	  	@remove="taxRemoved"
	  	></multiselect>
</template>
<script>
	import Vue from 'vue'
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	export default{
		props:[
			'value'
		],
		components:{
			Multiselect
		},
		data(){
			return {
				taxes:[],
				selected:null
			}
		},
		mounted(){
			let self = this
			Vue.axios('taxes')
			.then(response => {
				self.taxes = response.data
			})
		},
		methods:{
			taxSelected(){
				this.$emit('input',this.selected)
			},
			taxRemoved(tax){

			}
		},
		watch:{
			value(n,o){
				this.selected = n
			}
		}
	}
</script>