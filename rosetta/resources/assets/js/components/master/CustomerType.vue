<template>
	  <multiselect
	  	v-model="selected" 
	  	:options="options" 
	  	:searchable="true" 
	  	track-by="code"
	  	label="name"
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="allowEmpty"
	  	@select="typeSelected"
	  	@close="closed"
	  	@remove="remove"
	  	:disabled="disabled"
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
			type:Object,
			required:false,
			default: function(){
				return null
			}
		},
		allowEmpty:{
			type:Boolean,
			required:false,
			default:false
		},
		disabled:{
			type:Boolean,
			required:false,
			default:false
		},
		allowAll:{
			type:Boolean,
			required:false,
			default:false,
		}
	},
	
	data(){
		return {
			selected:null,
			options:[
				{
					code:"DISTRIBUTO",
					name:"Distributor"
				},
				{
					code:"DEALER",
					name:"Dealer"
				},
				{
					code:"SUPER_STOCKIST",
					name:"Super Stockist"
				},
				{
					code:"SUPER_STOCKIST_DISTRIBUTOR",
					name:"Super Stockist -> DISTRIBUTOR"
				},
				{
					code:"SUPER_STOCKIST_DEALER",
					name:"Super Stockist -> DEALER"
				}
			]
		}
	},
	mounted(){
		if(this.allowAll)
		{
			this.options.unshift({
				code:"ALL",
				name:"All"
			})
		}
		if(!this.allowEmpty)
		{
			this.selected = this.options[0]
			this.$emit('input',this.selected)
		}
	},
	methods:{
		remove(){
			if(this.allowAll)
			{
				this.selected = _.findWhere(this.options,{'code': 'ALL'})
				this.$emit('input',this.selected)
			}
			else
			{
				this.selected = null
				this.$emit('input',null)
			}
		},
		closed(){
			if(!this.selected)
				this.$emit('blur',null)
		},
    	typeSelected(type){
    		this.$emit('input',type)
    	}
	},
	watch:{
		value(){
			this.selected = this.value
		}
	},
}
</script>