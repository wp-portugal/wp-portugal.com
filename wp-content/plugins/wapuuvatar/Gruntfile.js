module.exports = function(grunt) {

	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		responsive_images: {
		    dev: {
		      files: [{
		        expand: true,
		        src: ['*.png'],
		        cwd: 'src/',
		        dest: 'dist/'
		      }],
		      options: {
		      	engine: "im",
			    sizes: [
		      	  {
		            name: '32',
		            width: 32,
		            height: 32
		          },
		          {
		            name: '64',
		            width: 64,
		            height: 64
		          },
		          {
		            name: "128",
		            width: 128,
		            height: 128
		          },
		          {
		            name: "256",
		            width: 256,
		            height: 256
		          }
		        ],
		      }
		    }
		  },
	});

	grunt.loadNpmTasks('grunt-responsive-images');

	// Default task(s).
	grunt.registerTask('default', ['responsive_images']);

};