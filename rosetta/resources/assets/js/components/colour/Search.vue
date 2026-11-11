<template>
      	<multiselect 
      	v-model="colour" 
      	:allow-empty="true" 
      	:options="colours" 
      	label="display_name" 
      	track-by="code" 
      	@select="colourSelected" 
      	@remove="colourRemoved" 
      	placeholder="Select Colour" 
      	:show-labels="false"
      	:loading="loading"
      	:disabled="(disabled!=null) || ((!collection) || (!colours.length))">
      	</multiselect>
</template>
<script>
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	import Vue from 'vue'
	export default{
		props:['value','collection','distributorId','dealerId','companyId','disabled'],
		components:{
			Multiselect
		},
		data(){
			return {
				colour:null,
				colours:[],
				loading:false,
			}
		},
		methods:{
			colourSelected(colour){
				this.$emit('input',colour)
			},
			colourRemoved(){
				this.$emit('input',null)	
			},
			loadColours(){
				let self = this
				let url = ""
				if(self.distributorId)
					url = `categories/${encodeURIComponent(self.collection.category_code)}/collections/${encodeURIComponent(encodeURIComponent(self.collection.code))}/colours/distributors/${self.distributorId}`
				else if(self.dealerId)
					url = `categories/${encodeURIComponent(self.collection.category_code)}/collections/${encodeURIComponent(encodeURIComponent(self.collection.code))}/colours/dealers/${self.dealerId}`
				else
					url = `categories/${encodeURIComponent(self.collection.category_code)}/collections/${encodeURIComponent(encodeURIComponent(self.collection.code))}/colours`
				this.loading = true
				Vue.axios.get(url)
				.then(response => {
					this.loading = false
					self.colours = response.data
					if(self.value)
					{
						self.colour = self.value
					}
					else
					{
						self.colour = null
						self.$emit('input',null)
					}
				})
			}
		},
		mounted(){
			if(this.collection)
			{
				this.loadColours()
			}
		},
		watch:{
			collection(n,o){
				let self = this
				if(n == null)
				{
					this.colour = null
					this.colours = []
					this.$emit('input',null)	
				}
				else
				{
					if(o)
					{
						if(n.code != o.code)
						{
							this.colour = null
							this.$emit('input',null)
						}
					}
					Vue.nextTick(function(){
						self.loadColours()
					})
					
				}
			},
			value(){
				if(this.value)
				{
					this.colour = this.value
				}
			}
		},
	}
</script>