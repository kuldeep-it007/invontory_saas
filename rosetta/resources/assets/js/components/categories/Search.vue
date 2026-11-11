<template>
      	<multiselect 
      	v-model="category" 
      	:allow-empty="true" 
      	:options="categories" 
      	label="display_name" 
      	track-by="code" 
      	@select="categorySelected" 
      	@remove="categoryRemoved" 
      	placeholder="Select Category"
      	:loading="loading"
      	:show-labels="false"
      	:disabled="disabled != null"
      	></multiselect>
</template>
<script>
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	import Vue from 'vue'
	export default{
		components:{
			Multiselect
		},
		props:['value','distributorId','dealerId','companyId','onlyCl','disabled'],
		data(){
			return {
				category:null,
				categories:[],
				loading:false,
			}
		},
		mounted(){
			let self = this
			let url = ""
			if(self.distributorId)
				url = `categories/distributors/${self.distributorId}`
			else if(self.dealerId)
				url = `categories/dealers/${self.dealerId}`
			else
				url = `categories`
			if(this.onlyCl)
				url = url + `?clonly`
			this.loading = true
			Vue.axios.get(url)
			.then(response => {
				this.loading = false
				self.categories = response.data
				if(self.value)
					self.category = self.value
			})
		},
		methods:{
			categorySelected(category){
				this.$emit('input',category)
			},
			categoryRemoved(){
				this.$emit('input',null)	
			},
			addCategory(category){
				this.categories.push(category)
				this.category = this.categories[this.categories.length -1]
				this.$emit('input',this.category)	
			},
		},
		watch:{
			value(){
				if(this.value == null)
					this.category = null
				else
					this.category = this.value
			}
		}
	}
</script>