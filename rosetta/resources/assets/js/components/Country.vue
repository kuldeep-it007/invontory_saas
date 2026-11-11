<template>
	<multiselect
	  	v-model="selected" 
	  	:options="countries" 
	  	:searchable="false" 
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="false"
	  	track-by="code"
	  	label="name"
	  	@select="countrySelected"
	  	></multiselect>
</template>
<script>
	import Vue from 'vue'
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	export default{
		props:{
			value:{
				type:[Object,null],
				required:false,
				default:function(){
					return null
				}
			}
		},
		components:{
			Multiselect
		},
		data(){
			return {
				countries:[],
				selected:null
			}
		},
		mounted(){
			let self = this
			Vue.axios('countries')
			.then(response => {
				self.countries = response.data
				if(!self.value)
				{
					self.selected = response.data[0]
					self.$emit('country:selected',self.selected)
					self.$emit('input',self.selected)

				}
			})
		},
		methods:{
			countrySelected(country){
				this.$emit('country:selected',country)
				this.$emit('input',country)
			}
		},
		watch:{
			value(n,o){
				this.selected = n
			}
		}
	}
</script>