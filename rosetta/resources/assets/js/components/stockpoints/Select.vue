<template>
      	<multiselect
      		v-model="stockpoint" 
      		:allow-empty="allowEmpty" 
      		:options="stockpoints" 
      		label="name" 
      		track-by="id" 
      		@select="stockpointSelected" 
      		placeholder="Select Stock Point"
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
			},
			company:{
				type: [Object,null],
				required: true
			}
		},
		components:{
			Multiselect
		},
		data(){
			return {
				stockpoint:null,
				stockpoints:[],
				loading:false,
			}
		},
		mounted(){
			this.loadStockPoints()
		},
		methods:{
			stockpointSelected(stockpoint){
				this.$emit('input',stockpoint)
			},
			loadStockPoints(){
				let self = this
				this.loading = true

				Vue.axios.get(`companies/${this.company.id}/stockpoints`)
				.then(response => {
					this.loading = false
					self.stockpoints = response.data
					if(this.value)
					{
						self.stockpoint = this.value
					}
					else
					{
						if(!this.allowEmpty)
						{
							self.region = self.stockpoints[0]
							this.$emit('input',self.stockpoint)
						}
					}
				})
			},
			remove(){
				if(this.allowEmpty)
					this.$emit('input',null)
			},
			regionSelected(region){
				this.$emit('input',stockpoint)
			}
		},
		watch:{
			value(){
				this.stockpoint = this.value
			},
			'company.id':function(){
				this.loadStockPoints()
			},
		}

	}
</script>