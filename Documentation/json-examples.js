var blog = {
		"_id": "ba772ffdfeedde2d95b8418a27002eaa",
		"flow3ObjectData": {
			"identifier": "ba772ffdfeedde2d95b8418a27002eaa",
			"classname": "\\F3\\Blog\\Domain\\Model\\Blog",
			"properties": {
				"title": {
					"type": "string",
					"multivalue": false,
					"value": "My first blog"
				},
				"posts": {
					"type": "SplObjectStorage",
					"multivalue": true,
					"value": [{
						"identifier": "abcdefg1",
						"classname": "\\F3\\Blog\\Domain\\Model\\Post"
					}, {
						"identifier": "abcdefg2",
						"classname": "\\F3\\Blog\\Domain\\Model\\Post"
					}]
				}
			}
		}
	},
	post = {
		"_id": "aa772ffdfeedde2d95b8418a27002eaa",
		"flow3ObjectData": {
			"identifier": "aa772ffdfeedde2d95b8418a27002eaa",
			"classname": "\\F3\\Blog\\Domain\\Model\\Post",
			"properties": {
				"title": {
					"type": "string",
					"multivalue": false,
					"value": "My first post"
				},
				"blog": {
					"type": "\\F3\\Blog\\Domain\\Model\\Blog",
					"multivalue": false,
					"value": {
						"identifier": "abcdefg1",
						"classname": "\\F3\\Blog\\Domain\\Model\\Blog"
					}
				}
			}
		}
	};

function(doc) {
	var aggregateRootClassNames = {
			'\\F3\\Blog\\Domain\\Model\\Blog': true,
			'\\F3\\Blog\\Domain\\Model\\Post': true
		}, data = doc.flow3ObjectData,
		propertyName;
	emit([data.classname, doc._id, 0], doc);

	for (propertyName in data.properties) {
		if (aggregateRootClassNames[data.properties[propertyName].type] && data.properties[propertyName].multivalue === false) {
			emit([data.properties[propertyName].value.classname, data.properties[propertyName].value.identifier, 1], doc);
		}
	}
}
