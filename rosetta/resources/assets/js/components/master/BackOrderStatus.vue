<template>
	<multiselect
	  	v-model="status" 
	  	:options="statuses" 
	  	:searchable="true" 
	  	:close-on-select="true" 
	  	:show-labels="false" 
	  	:allow-empty="allowEmpty"
	  	@select="statusSelected"
	  	placeholder="Select Status"
	  	@remove="remove"
	  	></multiselect>
</template>
<script>
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	export default{
		components:{
			Multiselect
		},
		props:{
			allowEmpty:{
				type:Boolean,
				required:false,
				default: false
			},
			value:{
				type:String
			}
		},
		data(){
			return {
				statuses:[
					"",
					"Open",
					"Partially Complete",
					"Complete"
				],
				status:""
			}
		},
		methods:{
			statusSelected(status){
				this.$emit('input',status)
			},
			remove(){
				this.$emit('input',"")
			}
		},
		watch:{
			value(n,o){
				this.status = this.value
			}
		}
	}
</script>