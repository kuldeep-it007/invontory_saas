<template>
	  <multiselect
	  	v-model="selected" 
	  	:options="options" 
	  	:searchable="true" 
	  	track-by="code"
	  	label="name"
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="false"
	  	@select="typeSelected"
	  	@close="closed"
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
	props:{
		value:{
			type:[Object,null],
			required:false,
			default: function(){
				return null
			}
		},
		company:{
			type:Object,
			required:true
		}
	},
	
	data(){
		return {
			selected:null,
			options:[]
		}
	},
	mounted(){
	//	console.log(this.company.id)
		this.loadTaxes()
		//
		/*this.options = this.master.territory_code
		if(this.value)
		{
			this.selected = this.value
		}
		else
		{
			this.selected = this.options[0]
			this.$emit('input',this.selected)
		}*/
	},
	methods:{
		closed(){
			if(!this.selected)
				this.$emit('blur',null)
		},
		loadTaxes(){
			let self = this
			Vue.axios.get(`companies/${this.company.id}/taxes`)
			.then(response => {
				self.options = response.data
				if(!self.value)
				{
					self.selected = self.options[0]
					self.$emit('input',self.selected)	
				}
				else
				{
					self.selected = self.value
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
		'company.id':function(){
			this.loadTaxes()
		}
	},
}
</script>