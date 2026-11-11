<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-lg" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Add Tax</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Region</label>
      							<region-select v-model="region" v-validate="'required'" data-vv-name="region"></region-select>
                                <span class="text-danger" v-show="errors.has('region')">Please select Region.</span>
      						</div>
      					</div>
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Category</label>
      							<category v-model="category" v-validate="'required'" data-vv-name="category"></category>
                                <span class="text-danger" v-show="errors.has('category')">Please select Category.</span>
      						</div>
      					</div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Collection</label>
                                <collection v-model="collection" :category="category"></collection>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Tax Type</label>
                                <tax-type v-model="tax_type" v-validate="'required'" data-vv-name="tax_type"></tax-type>
                                <span class="text-danger" v-show="errors.has('tax_type')">Please select Tax Type.</span>
                            </div>
                        </div>
      					<div class="col-md-3">
      						<div class="form-group" :class="errors.has('tax') ? 'has-error' : ''">
      							<label>Tax</label>
      							<div class="input-group">
  									<input type="text" class="form-control" v-model="tax_percent" v-validate="'required|decimal|min_value:0|max_value:100'" data-vv-name="tax">
  									<span class="input-group-addon" id="basic-addon2">%</span>
								</div>
      						</div>
      					</div>
      				</div>
      			</div>
      			<div class="modal-footer">
      				<button class="btn btn-sm btn-primary" @click="add">ADD</button>
      			</div>
      		</div>
      	</div>
    </div>
</template>
<script>
    import Vue from 'vue'
	import RegionSelect from '../../region/Select'
	import Category from '../../categories/Search'
    import Collection from '../../collections/Search'
    import TaxType from '../../TaxTypeSingle'
	import VeeValidate from 'vee-validate'
    import {EventBus} from '../../../configs/EventBus'
    Vue.use(VeeValidate)
	export default {
		components:{
			RegionSelect,
			Category,
            Collection,
            TaxType
		},
		data(){
			return {
                region:{
                    id:null,
                    name: "",
                    country:null,
                    taxTypes:[],
                },
				category:null,
                collection:null,
				tax_percent:0,
                tax_type:null
			}
		},
   /*     mounted(){
            let self = this
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                this.region = null
                this.category = null    
                this.tax_percent = 0
                this.tax_type = null
                this.errors.clear()
            })
        },*/
		methods:{
            add(){
                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        let input = {
                            tax_percent : this.tax_percent,
                            category_code : this.category.code,
                            collection_code : this.collection ? this.collection.code : null,
                            tax_type_code : this.tax_type.code
                        }
                        EventBus.$emit('loader:show')
                        Vue.axios.post(`regions/${this.region.id}/add_product_taxes`,input)
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            swal({
                                title:"Tax added",
                                icon:"success"
                            })
                            this.$emit('product_tax:added',response.data)
                            $(this.$refs.modal).modal('hide')
                        })
                        .catch(error => {
                            EventBus.$emit('loader:show')
                            if(error.response.status == 409)
                            {
                                swal({
                                    title: "Error",
                                    text:error.response.data.error,
                                    icon:'error'
                                })
                                .then(value => {
                                    this.region = null
                                    this.category = null 
                                    this.collection = null    
                                    this.tax_percent = 0
                                    this.tax_type = null
                                })
                            }
                        })
                    }
                })
            },
			show(){
                this.region = null
                this.category = null    
                this.collection = null    
                this.tax_percent = 0
                this.tax_type = null
                this.errors.clear()
				$(this.$refs.modal).modal('show')
			}
		},
	}
</script>

