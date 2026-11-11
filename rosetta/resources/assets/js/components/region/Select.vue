<template>
      	<multiselect
      		v-model="region" 
      		:allow-empty="allowEmpty" 
      		:options="regions" 
      		label="name" 
      		track-by="id" 
      		@select="regionSelected" 
      		placeholder="Select Region"
      		:show-labels="false"
      		:loading="loading"
      		@remove="remove"></multiselect>
</template>
<script>
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	import Vue from 'vue'
	export default{
		props:{
			value:{
				type:[Object,null],
				required:false
			},
			allowEmpty:{
				type:Boolean,
				required:false,
				default:false,
			}
		},
		components:{
			Multiselect
		},
		data(){
			return {
				region:null,
				regions:[],
				loading:false,
			}
		},
		mounted(){
			let self = this
			this.loading = true

			Vue.axios.get('regions')
			.then(response => {
				this.loading = false
				self.regions = response.data
				if(this.value)
				{
					self.region = this.value
				}
				else
				{
					if(!this.allowEmpty)
					{
						self.region = self.regions[0]
						this.$emit('input',self.region)
					}
				}
			})
		},
		methods:{
			remove(){
				if(this.allowEmpty)
					this.$emit('input',null)
			},
			regionSelected(region){
				this.$emit('input',region)
			}
		},
		watch:{
			value(){
				this.region = this.value
			}
		}

	}
</script>