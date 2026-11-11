<template>
      	<multiselect 
      	v-model="collection" 
      	:allow-empty="true" 
      	:options="collections" 
      	label="display_name" 
      	track-by="code" 
      	@select="collectionSelected" 
      	@remove="collectionRemoved" 
      	placeholder="Select Collection" 
      	:show-labels="false"
      	:loading="loading"
      	:disabled="(disabled!=null) || ((!category) || (!collections.length))"></multiselect>
</template>
<script>
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	import Vue from 'vue'
	export default{
		props:['value','category','distributorId','dealerId','companyId','disabled'],
		components:{
			Multiselect
		},
		data(){
			return {
				collection:null,
				collections:[],
				loading:false,
			}
		},
		methods:{
			collectionSelected(collection){
				this.$emit('input',collection)
			},
			collectionRemoved(){
				this.$emit('input',null)	
			},
			loadCollections(){
				let self = this
				let url = ""
				if(self.distributorId)
					url = `categories/${self.category.code}/collections/distributors/${self.distributorId}`
				else if(self.dealerId)
					url = `categories/${self.category.code}/collections/dealers/${self.dealerId}`
				else
					url = `categories/${self.category.code}/collections`
				this.loading = true
				Vue.axios.get(url)
				.then(response => {
					this.loading = false
					self.collections = response.data
					if(self.value)
					{
						self.collection = self.value
					}
					else
					{
						self.collection = null
					}
				})
			}
		},
		mounted(){
			if(this.category)
			{
				this.loadCollections()
			}
		},
		watch:{
			category(n,o){
				let self = this
				if(n == null)
				{
					this.collection = null
					this.collections = []
					this.$emit('input',null)
				}
				else
				{
					if(o)
					{
						if(n.code != o.code)
						{
							this.collection = null
							this.$emit('input',null)
						}
					}
					Vue.nextTick(function(){
						self.loadCollections()
					})
				}
			},
			value(){
				if(this.value)
				{
					this.collection = this.value
				}
			}
		}

	}
</script>