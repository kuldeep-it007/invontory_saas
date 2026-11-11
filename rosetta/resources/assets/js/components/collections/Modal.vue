<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-sm" role="document">
    		<div class="modal-content">
   			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Add Collection</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-12">
      						<div class="form-group">
      							<label>Code</label>
      							<input type="text" class="form-control" v-model="code" v-validate="'required'" data-vv-name="code" name="code">
                           		<span class="text-red" v-if="errors.has('code')">Please enter code</span>
      						</div>
      					</div>
      				</div>
      				<div class="row">
      					<div class="col-md-12">
      						<div class="form-group">
      							<label>Description</label>
      							<input type="text" class="form-control" v-model="description" name="description">
                           </div>
      					</div>
      				</div>
                  <div class="row">
                 		<div class="col-md-8">
                       	<div class="form-group">
                       		<label>In Multiples</label>
                       		<input type="text" class="form-control" v-model="in_multiples" data-vv-name="in_multiples" name="in_multiples">
                       	</div>
                   	</div>
                 	</div>
                 	<div class="row">
                 		<div class="col-md-8">
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
	export default {
        components: {
		},
		data(){
			return {
				code:null,
	            description:null,
	            in_multiples:null,
	            plank_size:null,
			}
		},
        mounted(){
         	let self = this
		        $(this.$refs.modal).on('hidden.bs.modal', function (e) {
		            self.code = null
		            self.description = null
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
	            }
	            if(this.in_multiples == 0)
	            	input.in_multiples = null
	            else
	            	input.in_multiples = this.in_multiples
	            if(this.plank_size == 0)
	            	input.plank_size = null
	            else
	            	input.plank_size = this.plank_size
	            this.$validator.validateAll()
	            .then(result => {
	               	if(result)
	               	{
		                EventBus.$emit('loader:show')
		                Vue.axios.post(`categories/${encodeURIComponent(this.$route.params.category_code)}/collections`,input)
		                .then(response => {
		                    swal({
		                        title:"Collection added successfully!",
		                        icon:"success"
		                    })
		                    this.$emit('collection:added',response.data)
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

