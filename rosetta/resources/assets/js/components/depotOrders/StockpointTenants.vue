<template>
      	<multiselect 
      	v-model="tenant" 
      	:allow-empty="true" 
      	:options="tenants" 
      	label="name" 
      	track-by="name" 
      	@select="tenantSelected" 
      	@remove="tenantRemoved" 
      	placeholder="Select Tenant" 
      	:show-labels="false"
      	:loading="loading"
      	:disabled="(disabled!=null) || ((!stockPoint) || (!tenants.length))"></multiselect>
</template>
<script>
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	import Vue from 'vue'
	export default{
		props:['value','stockPoint','disabled'],
		components:{
			Multiselect
		},
		data(){
			return {
				tenant:null,
				tenants:[],
				loading:false,
			}
		},
		methods:{
			tenantSelected(tenant){
				this.$emit('input',tenant)
			},
			tenantRemoved(){
				this.$emit('input',null)	
			},
			loadTenants(){
				let self = this
				if(!this.stockPoint)
					return
				this.loading = true
				Vue.axios.get(`stock_point_tenants?query=${this.stockPoint.code}`)
				.then(response => {
					this.loading = false
					self.tenants = response.data
					if(self.value)
					{
						self.tenant = self.value
					}
					else
					{
						self.tenant = null
					}
				})
			}
		},
		mounted(){
			if(this.stockPoint)
			{
				this.loadTenants()
			}
		},
		watch:{
			stockPoint(n,o){
				this.$emit('input',null)
				this.tenant = null
				if(n){	
					this.loadTenants()
				}
			},
			value(){
				if(this.value)
				{
					this.tenant = this.value
				}
			}
		}

	}
</script>