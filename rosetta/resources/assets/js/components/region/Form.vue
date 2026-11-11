<template>
	<div v-if="abilities.regions">
		<section class="content-header">
			<h1 v-if="region.id">Edit Region</h1>
			<h1 v-else>Create Region</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body">
							<div class="col-md-12">
								<div class="form-group" :class="{'has-error':errors.has('name')}">
									<label>Name</label>
									<input type="text" class="form-control" v-model="region.name" v-validate="'required'" data-vv-name="name">
									<span class="text-danger" v-show="errors.has('name')">Please enter name.</span>
								</div>
								<div class="form-group">
									<label>Country</label>
									<country v-model="region.country"></country>
								</div>
								<div class="form-group">
									<label>Structure</label>
									<tax-type v-model="region.taxTypes"></tax-type>
								</div>
								<div class="form-group">
									<label style="display: block;">Tax Information</label>
									
										<div class="checkbox">
											<label>
          										<input type="checkbox" value="tax" v-model="region.tax_information"> Tax
        									</label>
										</div>

										<div class="checkbox">
											<label>
          										<input type="checkbox" value="gst" v-model="region.tax_information"> GST
        									</label>
										</div>
										<div class="checkbox">
											<label>
          										<input type="checkbox" value="pan" v-model="region.tax_information"> PAN Details
        									</label>
										</div>
										<div class="checkbox">
											<label>
          										<input type="checkbox" value="vat" v-model="region.tax_information"> VAT
        									</label>
										</div>
									
								</div>
							</div>
						</div>
						<div class="box-footer">
							<button class="btn btn-primary" @click="save">SAVE</button>
							<button class="btn btn-default" @click="cancel">CANCEL</button>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import VeeValidate from 'vee-validate'
	import Vue from 'vue'
	import Country from '../Country'
	import TaxType from '../TaxType'
	import {EventBus} from '../../configs/EventBus'
	Vue.use(VeeValidate)
	export default{
		mixins : [appMixin],
		components:{
			Country,
			TaxType
		},
		data(){
			return {
				region:{
					id:null,
					name: "",
					country:null,
					taxTypes:[],
					tax_information:[],
				}
			}
		},
		mounted(){
			if(this.$route.params.id)
			{
				this.region.id = this.$route.params.id
			}
			if(this.region.id)
			{
				EventBus.$emit('loader:show')
				Vue.axios.get(`regions/${this.region.id}`)
				.then(response => {
					EventBus.$emit('loader:hide')
					this.region = response.data
				})
			}
		},
		methods:{
			cancel(){
				this.$router.push({name:'regions'})
			},
			saveData(){
				let out = {}
				out.name = this.region.name
				out.country = this.region.country.code
				out.tax_information = this.region.tax_information
				if(this.region.taxTypes.length)
				{
					out.tax_types = _.pluck(this.region.taxTypes,'code')
				}
				return out
			},
			save(){
				let self = this
				//this.error.clear()
				this.$validator.validateAll().then(result => {
    				if(result == true)
    				{
    					if(self.region.id)
    					{
    						Vue.axios.put(`regions/${self.region.id}`,this.saveData())
	    					.then(response => {
	    						swal ( "Success" ,  "Region updated" ,  "success" )
	    						.then(value =>{
	    							self.$router.push({name:'regions'})
	    						})
	    					})
    					}
    					else
    					{
    						Vue.axios.post('regions',this.saveData())
	    					.then(response => {
	    						swal ( "Success" ,  "Region added" ,  "success" )
	    						.then(value =>{
	    							self.$router.push({name:'regions'})
	    						})
	    					})	
    					}
    					
    				}
    			})
				 
			}
		}
	}
</script>