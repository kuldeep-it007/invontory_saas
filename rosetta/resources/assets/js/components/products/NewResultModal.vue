<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog" role="document">
    		<div class="modal-content">
      			<div class="modal-body">
                    <template v-if="validResult">
                        <div class="row" v-if="!$route.params.id">
                            <div class="col-md-12">
                                <p>
                                    Please review result below. Make sure that NAV ID for ROS and all companies are matching. In case of any discrepancies please correct those before adding new product
                                </p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="info-box" :class="result.product ? 'bg-green' : 'bg-red'">
                                    <span class="info-box-icon"><i class="fa" :class="result.product ? 'fa-check-circle' : 'fa-exclamation-circle'"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ROS</span>
                                        <span class="info-box-number">
                                            <template v-if="result.product">
                                                <strong>Product ID:</strong> {{result.product.id}} | <strong>NAV ID:</strong> {{result.product.nav_id}}
                                            </template>
                                            <template v-else>
                                                Product was not created in any of the companies in NAV.
                                            </template>
                                        </span>
                                    </div>
                                    <!-- /.info-box-content -->
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-box" :class="result.cms ? 'bg-green' : 'bg-red'">
                                    <span class="info-box-icon"><i class="fa" :class="result.cms ? 'fa-check-circle' : 'fa-exclamation-circle'"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">CMS/PORTAL</span>
                                        <span class="info-box-number">
                                            <template v-if="!result.cms">
                                                Product was not created in any of the companies in NAV.
                                            </template>
                                        </span>
                                    </div>
                                    <!-- /.info-box-content -->
                                </div>
                            </div>
                            <div class="col-md-12" v-for="companyResult in result.companies" :key="companyResult.company.id">
                                <div class="info-box" :class="companyResult.item_no ? 'bg-green' : 'bg-red'">
                                    <span class="info-box-icon"><i class="fa" :class="companyResult.item_no ? 'fa-check-circle' : 'fa-exclamation-circle'"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{companyResult.company.name}}</span>
                                        <span class="info-box-number">
                                            <template v-if="companyResult.item_no">
                                                <strong>NAV ID:</strong> {{companyResult.item_no}}
                                            </template>
                                            <template v-else>
                                                {{companyResult.error}}
                                            </template>
                                        </span>
                                    </div>
                                    <!-- /.info-box-content -->
                                </div>
                            </div>
                        </div>    
                    </template>
                    
      			</div>
      			<div class="modal-footer">
      				<button type="button" class="btn btn-sm btn-default" @click="closeModal">OK</button>
      			</div>
      		</div>
      	</div>
    </div>
</template>
<script>
    export default{
        data(){
            return {
                result:{}
            }
        },
        computed:{
            validResult(){
                return !_.isEmpty(this.result)
            }
        },
        methods:{
            closeModal(){
                $(this.$refs.modal).modal('hide')
                this.$emit('product-result:closed')
            },
            show(result){
                this.result = Object.assign({},result)
                $(this.$refs.modal).modal('show')
            }
        }
    }
</script>
<style scoped>
    .info-box-text{
        font-size: 14px
    }
    .info-box-number{
        font-size: 12px;
        font-weight: normal;
    }
    .info-box-content{
        margin-left: 60px;
    }
    .info-box-icon{
        height: 60px;
        width: 60px;
        line-height: 60px;
    }
    .info-box{
        min-height: 60px;
    }
</style>