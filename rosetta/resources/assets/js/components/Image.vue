<template>
	<div class="row">
		<div class="col-md-8">
			<div class="input-group">
				<input type="text" class="form-control image-preview-filename" disabled="disabled">
				<span class="input-group-btn">
					<button type="button" class="btn btn-default image-preview-clear" style="display:none;">
						<span class="glyphicon glyphicon-remove"></span>Clear
					</button>
					<div class="btn btn-default image-preview-input">
						<span class="glyphicon glyphicon-folder-open"></span>
						<span class="image-preview-input-title">Browse</span>
						<input id="category_image" type="file" accept="image/png, image/jpeg, image/gif" name="category_image"/>
					</div>
				</span>
			</div>
		</div>
		<div class="col-md-4">
			<div class="image-preview text-right">
				<img :src="src" v-if="imageLoaded">
			</div>
		</div>
	</div>
</template>
<script>
	import {dataURLToBlob,blobToBase64String} from 'blob-util'
	export default{
		props:{
			logo:{
				type:[String,null],
				required:false
			}
		},
		data(){
			return {
				imageLoaded:false,
				src:""
			}
		},
		mounted(){
			let self = this
			if(self.logo)
			{
				self.src = self.logo
				self.imageLoaded = true
			}
			$('.image-preview-clear').click(function(){
				$('.image-preview-filename').val("");
				$('.image-preview-clear').hide();
				$('.image-preview-input input:file').val("");
				$(".image-preview-input-title").text("Browse");
				self.imageLoaded = false
				self.$emit('image:changed',null)
			}); 
			// Create the preview image
			$(".image-preview-input input:file").change(function (){     
				var file = this.files[0];
				var reader = new FileReader();
				reader.onload = function (e) {
					self.imageLoaded = true
					$(".image-preview-input-title").text("Change");
					$(".image-preview-clear").show();
					$(".image-preview-filename").val(file.name);
					self.src = e.target.result;
					let blob = dataURLToBlob(e.target.result)
					blobToBase64String(blob)
						.then(function (base64String) {
							self.$emit('image:changed',base64String)
					})
					.catch(function (err) {})
					/*dataURLToBlob(e.target.result)
					.then(function (blob) {
  						blobToBase64String(blob)
  						.then(function (base64String) {
  							self.$emit('image:changed',base64String)
						})
						.catch(function (err) {});
					}).catch(function (err) {});*/
				}        
				reader.readAsDataURL(file);
			});
		},
		watch:{
			logo(){
				if(this.logo)
				{
					this.src = this.logo
					this.imageLoaded = true
				}
			}
		}
	}
</script>
<style>
.image-preview-input {
    position: relative;
  overflow: hidden;
  margin: 0px;    
    color: #333;
    background-color: #fff;
    border-color: #ccc;    
}
.image-preview-input input[type=file] {
  position: absolute;
  top: 0;
  right: 0;
  margin: 0;
  padding: 0;
  font-size: 20px;
  cursor: pointer;
  opacity: 0;
  filter: alpha(opacity=0);
}
.image-preview-input-title {
    margin-left:2px;
}
.image-preview{
	height: 150px;
}
.image-preview img{
	max-width: 150px;
	max-height: 150px;
	padding: 5px;
    border: 1px solid #CCC;
}
</style>