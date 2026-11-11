<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static" v-if="abilities.addStock">
  		<div class="modal-dialog modal-md" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Import Stock</h4>
      			</div>
      			<div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                Please Note : Existing Stock will be deleted before importing Stock from the sheet provided!
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group" :class="errors.has('file') ? 'has-error' : ''">
                                <input type="file" ref="file" v-validate="'required|size:2048|mimes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel'" data-vv-name="file" accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                <p class="text-red" v-if="errors.has('file')">Make sure that you have selected Excel spreadsheet and file size does not exceed 2MB</p>
                            </div>
                        </div>
                    </div>
                    <div class="row" v-if="failed.length">
                        <div class="col-md-12">
                            <p>Import failed for following records</p>
                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Product ID</th>
                                        <th>Category</th>
                                        <th>Collection</th>
                                        <th>Colour</th>
                                        <th>Batch No</th>
                                        <th>Quantity</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="record in failed" :key='record.row'>
                                        <td>{{record.row}}</td>
                                        <td>{{record.nav_id}}</td>
                                        <td>{{record.category}}</td>
                                        <td>{{record.collection}}</td>
                                        <td>{{record.colour}}</td>
                                        <td>{{record.batch_no}}</td>
                                        <td>{{record.quantity}}</td>
                                        <td>{{record.reason}}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
      			</div>
      			<div class="modal-footer">
      				<a class="btn btn-default" href="#" @click.prevent="importStock"><i class="fas fa-upload"></i> Import</a>
      			</div>
      		</div>
      	</div>
    </div>
</template>
<script>
  import Vue from 'vue'
  import appMixin from '../../vuex-mixin.js'
  import VeeValidate from 'vee-validate'
  import {EventBus} from '../../configs/EventBus'
  Vue.use(VeeValidate)
	export default {
        mixins : [appMixin],
		components:{},
        props:['superStockistId'],
    		data(){
    			return {
                    failed:[]
    			}
    		},
        mounted(){
            let self = this
            $(self.$refs.modal).on('shown.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                    self.failed=[]
                })
            })
            $(self.$refs.modal).on('hidden.bs.modal', function (e) {
                self.failed=[]
            })
        },
    	methods:{
            importStock(){
                this.errors.clear()
                this.$validator.validateAll()
                .then(v => {
                    if(v)
                    {
                        let file = this.$refs.file.files[0]
                        let data = new FormData()
                        data.append('file',file)
                        let url = ''
                        if(!this.isCustomer)
                            url = `super_stockists/${this.superStockistId}/import_stock`
                        EventBus.$emit('loader:show')
                        this.failed=[]
                        Vue.axios.post(url,data)
                        .then(response => {
                            if(response.data.invalid)
                            {
                                EventBus.$emit('loader:hide')
                                this.failed = response.data.invalid
                            }
                            else
                            {
                                swal({
                                    title:"Stock imported",
                                    icon:"success"
                                })
                                .then(a => {
                                    $(this.$refs.modal).modal('hide')
                                })
                            }
                            this.$refs.file.value = ''
                            this.$emit('stock-imported')
                        })
                        .catch(error => {
                            if(error)
                            {
                                console.log(error)
                                swal({
                                    title: "Error",
                                    text:error.response.data.error,
                                    icon:'error'
                                })
                            }
                        })
                    }
                })
                
            },
    		show(){
    			$(this.$refs.modal).modal('show')
    		}
		},
	}
</script>

