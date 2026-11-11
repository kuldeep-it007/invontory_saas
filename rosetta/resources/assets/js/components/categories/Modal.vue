<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog" role="document">
    		<div class="modal-content">
   			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Add Category</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-4">
      						<div class="form-group">
      							<label>Code</label>
      							<input type="text" class="form-control" v-model="code" v-validate="'required'" data-vv-name="code" name="code">
                           <span class="text-red" v-if="errors.has('code')">Please enter code</span>
      						</div>
      					</div>
      					<div class="col-md-8">
      						<div class="form-group">
      							<label>Description</label>
      							<input type="text" class="form-control" v-model="description" name="description">
                           </div>
      					</div>
                  </div>
                  <div class="row">
                     <div class="col-md-3">
                        <div class="form-group text-center">
                           <label style="display: block; margin-bottom: 15px;">Allow CL</label>
                           <switches v-model="allow_cl" theme="bootstrap" color="success"></switches>
                        </div>
                     </div>
                     <div class="col-md-3">
                        <div class="form-group" :class="errors.has('cl_percent') ? 'has-error' : ''">
                           <label>CL Percent</label>
                           <div class="input-group">
                              <input type="text" class="form-control" v-model="cl_percent" v-validate="'decimal|required'" data-vv-name="cl_percent" :disabled="!allow_cl">
                              <span class="input-group-addon" id="basic-addon2">%</span>
                           </div>
                        </div>
                     </div>
                     <div class="col-md-3">
                        <div class="form-group">
                           <label>In Multiples</label>
                           <input type="text" class="form-control" v-model="in_multiples" data-vv-name="in_multiples" name="in_multiples">
                        </div>
                     </div>
                     <div class="col-md-3">
                        <div class="form-group">
                           <label>Plank Size</label>
                           <input type="text" class="form-control" v-model="plank_size" data-vv-name="plank_size" name="plank_size">
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
   import {EventBus} from '../../configs/EventBus'
   import Switches from 'vue-switches'
	export default {
      components: {
        Switches
    },
		data(){
			return {
				code:null,
            	description:null,
				allow_cl:false,
            	cl_percent:null,
            	in_multiples:null,
               plank_size:null,
			}
		},
      mounted(){
	        let self = this
	        $(this.$refs.modal).on('hidden.bs.modal', function (e) {
	            self.code = null
	            self.description = null
	            self.cl_percent = null 
	            self.allow_cl =false
	            self.in_multiples = null
               self.plank_size = null
	        })
	        $(this.$refs.modal).on('show.bs.modal', function (e) {
	            Vue.nextTick(function(){
	                self.errors.clear()
	            }) 
	        })
      	},
		methods:{
         add(){
            let self = this
            let input = {
               code : this.code,
               description : this.description,
               allow_cl : this.allow_cl,
            }
            if(this.in_multiples == 0)
	           	input.in_multiples = null
	         else
	           	input.in_multiples = this.in_multiples
            if(this.plank_size == 0)
               input.plank_size = null
            else
               input.plank_size = this.plank_size
	         if(this.cl_percent == 0)
	           	input.cl_percent = null
	         else
	           	input.cl_percent = this.cl_percent
            this.$validator.validateAll()
            .then(result => {
               if(result)
               {
                  EventBus.$emit('loader:show')
                  Vue.axios.post(`categories`,input)
                  .then(response => {
                     swal({
                        title:"Category added successfully!",
                        icon:"success"
                     })
                     this.$emit('category:added',response.data)
                     $(this.$refs.modal).modal('hide')
                  })
                  .catch(error => {
                     EventBus.$emit('loader:show')
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

