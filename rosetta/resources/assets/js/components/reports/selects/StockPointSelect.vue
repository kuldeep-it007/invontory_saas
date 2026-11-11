<template>
	  <multiselect
	  	v-model="selected" 
		track-by="id"
		label="name"
	  	:options="options" 
	  	:searchable="true" 
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="false"
	  	@select="typeSelected"
	  	@remove="remove"
	  	placeholder="Select Stockpoint"
	  	:loading="loading"
	  	></multiselect>
</template>
<script>
import Multiselect from 'vue-multiselect'
import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
import Vue from 'vue'
import AppMixin from '../../../vuex-mixin.js'
export default{
	mixins:[AppMixin],
	components:{
		Multiselect
	},
	props:{
        company:{
            type : Object,
            required: true,
        },
		tenant:{
			type: String,
			required: true,
			default:""
		}
    },
	data(){
		return {
			selected:null,
			options:[],
			disable:false,
			loading:false
		}
	},
	mounted(){
		this.loadStockpoints()
	},
	methods:{
		remove(){
			this.$emit('input',null)
		},
		loadStockpoints(){
			if(!this.tenant)
				return
			let self = this
			self.selected = null
			this.loading = true
			Vue.axios.get(`companies/${this.company.id}/thresholdtenants/${this.tenant}/stockpoints`)
			.then(response => {
				this.loading = false
				self.options = response.data
				if(this.value)
				{
					self.selected = this.value
				}
				else
				{
					self.selected = self.options[0]
					this.$emit('input',self.selected)
				}
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
		tenant(){
			Vue.nextTick(() => {
				this.loadStockpoints()
			})
			
		}
	},
}
</script>