module.exports = function( grunt ) {
    grunt.initConfig( {
        pkg: grunt.file.readJSON( "package.json" ),

        phpcs: {
            plugin: {
                src: [ "./*.php", "./includes/*.php" ]
            },
            options: {
                bin: "vendor/bin/phpcs --extensions=php --ignore=\"*/vendor/*,*/node_modules/*\"",
                standard: "phpcs.ruleset.xml"
            }
        },

        jscs: {
            scripts: {
                src: [ "Gruntfile.js", "src/js/*.js" ],
                options: {
                    preset: "jquery",
                    requireCamelCaseOrUpperCaseIdentifiers: false, // We rely on name_name too much to change them all.
                    maximumLineLength: 250
                }
            }
        },

        jshint: {
            grunt_script: {
                src: [ "Gruntfile.js" ],
                options: {
                    curly: true,
                    eqeqeq: true,
                    noarg: true,
                    quotmark: "double",
                    undef: true,
                    unused: false,
                    node: true     // Define globals available when running in Node.
                }
            }
        }
    } );

	grunt.loadNpmTasks( "grunt-jscs" );
	grunt.loadNpmTasks( "grunt-contrib-jshint" );
	grunt.loadNpmTasks( "grunt-phpcs" );

    // Default task(s).
    grunt.registerTask( "default", [ "phpcs", "jscs", "jshint" ] );
};
