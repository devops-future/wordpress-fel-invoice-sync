"use strict";

const { json } = require('body-parser');
const { debug } = require('request');

module.exports = (core,db,modules,module,controllers,models) => {
	class Main {
		
		constructor (){
		}
		
		static getInstance (){
			if(typeof this.__instance == 'undefined'){
				this.__instance = new Base();
			}
			return this.__instance;
		}

		// Solicitud de Token
		static async token(){
			let obj = {
				SolicitaTokenRequest:{
					usuario: process.env.FEL_USER,
					apikey: process.env.FEL_APIKEY
				}
			};
			
			let builder = new xml2js.Builder();
			let xml = builder.buildObject(obj);

			let xmlResult = await fetch('https://dev.api.ifacere-fel.com/fel-dte-services/api/solicitarToken', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/xml'
				},
				body: xml
			}).then(response => response.text());

			let parser = new xml2js.Parser();

			return new Promise((resolve, reject) =>{
				parser.parseString(xmlResult, function(err, result){
					if(err || result.SolicitaTokenResponse.tipo_respuesta == 1){
						reject({
							status: false,
							message: err.message,
							data: result
						});
					}else{
						resolve({
							status: true,
							message: 'token',
							data: {
								token: 'Bearer ' + result.SolicitaTokenResponse.token[0],
								vigencia: result.SolicitaTokenResponse.vigencia[0]
							}
						});
					}
				});
			});
		}

		// Solicitud de Firma electronica
		static async firma(xml){
			let token = await Main.token();

			let xmlResult = await fetch('https://dev.api.soluciones-mega.com/api/solicitaFirma', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/xml;charset=UTF-8',
        			'Authorization': token.data.token
				},
				body: `<?xml version="1.0" encoding="UTF-8"?>
						<FirmaDocumentoRequest id="A3FD2363-05C2-AB7B-373D-56C08CF892B6">
							<xml_dte>
								<![CDATA[
									`+xml+`
								]]>
							</xml_dte>
						</FirmaDocumentoRequest>`
			}).then(response => response.text());

			let parser = new xml2js.Parser();

			return new Promise((resolve, reject) =>{
				parser.parseString(xmlResult, function(err, result){
					if(err || result.FirmaDocumentoResponse.tipo_respuesta == 1){
						reject({
							status: false,
							message: 'error al firmar documento',
							data: result
						});
					}else{
						resolve({
							status: true,
							message: 'documento firmado',
							data: result
						});
					}
				});
			});
		}

		// Solicitud de registro de documento
		static async registra(xml){
			let token = await Main.token();

			let xmlResult = await fetch('https://dev2.api.ifacere-fel.com/api/registrarDocumentoXML', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/xml;charset=UTF-8',
        			'Authorization': token.data.token
				},
				body: `<?xml version="1.0" encoding="UTF-8"?>
						<RegistraDocumentoXMLRequest id="166437D6-0BE3-467C-947C-EC8018DB0A03">
							<xml_dte>
								<![CDATA[
									`+xml+`
								]]>
							</xml_dte>
						</RegistraDocumentoXMLRequest>`
			}).then(response => response.text());

			let parser = new xml2js.Parser();
			
			return new Promise((resolve, reject) =>{
				parser.parseString(xmlResult, function(err, result){
					if(err || result.RegistraDocumentoXMLResponse.tipo_respuesta == 1){
						reject({
							status: false,
							message: 'error al registrar documento',
							data: result
						});
					}else{
						resolve({
							status: true,
							message: 'documento registrado',
							data: result
						});
					}
				});
			});
		}

		// proceso de facturacion electronica
		static async factura(req, res){
			const obj = {
				'dte:GTDocumento': {
					$:{
						'xmlns:dte': "http://www.sat.gob.gt/dte/fel/0.2.0",
						'xmlns:xd': "http://www.w3.org/2000/09/xmldsig#",
						'Version': "0.1"		
					},
					'dte:SAT': {
						$:{
							'ClaseDocumento': "dte"
						},
						'dte:DTE': {
							$:{
								'ID': "DatosCertificados"
							},
							'dte:DatosEmision':{
								$: {
									'ID': "DatosEmision"
								},
								'dte:DatosGenerales': {
									$: {
										'CodigoMoneda': "GTQ",
										'FechaHoraEmision': req.body.datosGenerales.fechaEmision,
										'Tipo': "FACT"
									}
								},
								'dte:Emisor': {
									$: {
										'AfiliacionIVA': req.body.emisor.afiliacionIVA,
										'CodigoEstablecimiento': req.body.emisor.codigoEstablecimiento,
										'NITEmisor': req.body.emisor.nit,
										'NombreComercial': req.body.emisor.NombreComercial,
										'NombreEmisor': req.body.emisor.nombreEmisor
									},
									'dte:DireccionEmisor': {
										'dte:Direccion': req.body.emisor.direccionEmisor.direccion,
										'dte:CodigoPostal': req.body.emisor.direccionEmisor.codigoPostal,
										'dte:Municipio': req.body.emisor.direccionEmisor.municipio,
										'dte:Departamento': req.body.emisor.direccionEmisor.departamento,
										'dte:Pais': req.body.emisor.direccionEmisor.pais
									}
								},
								'dte:Receptor': {
									$: {
										'CorreoReceptor': req.body.receptor.correoReceptor,
										'IDReceptor': req.body.receptor.idReceptor,
										'NombreReceptor': req.body.receptor.nombreReceptor
									},
									'dte:DireccionReceptor': {
										'dte:Direccion': req.body.receptor.direccionReceptor.direccion,
										'dte:CodigoPostal': req.body.receptor.direccionReceptor.codigoPostal,
										'dte:Municipio': req.body.receptor.direccionReceptor.municipio,
										'dte:Departamento': req.body.receptor.direccionReceptor.departamento,
										'dte:Pais': req.body.receptor.direccionReceptor.pais
									}
								},
								'dte:Frases': {
									'dte:Frase': {
										$: {
											'CodigoEscenario': "1",
											'TipoFrase': "1" 
										}
									}
								},
								'dte:Items': {
									'dte:Item':[]
								},
								'dte:Totales': {
									'dte:TotalImpuestos': {
										'dte:TotalImpuesto': {
											$: {
												'NombreCorto': req.body.totales.totalImpuestos.nombreCorto,
												'TotalMontoImpuesto': req.body.totales.totalImpuestos.totalMontoImpuesto
											}
										}
									},
									'dte:GranTotal': req.body.totales.granTotal
								}
							}
						}
					}
				}
			};

			var arrItems = obj['dte:GTDocumento']['dte:SAT']['dte:DTE']['dte:DatosEmision']['dte:Items']['dte:Item'] = [];
			
			for(var key in req.body.items){
				let item = req.body.items[key];
				arrItems.push({
					$: {
						"BienOServicio": item.bienOServicio,
						"NumeroLinea": key
					},
					"dte:Cantidad": item.cantidad,
					"dte:UnidadMedida": item.unidadMedida,
					"dte:Descripcion": item.descripcion,
					"dte:PrecioUnitario": item.precioUnitario,
					"dte:Precio": item.precio,
					"dte:Descuento": item.descuento,
					"dte:Impuestos": {
						"dte:Impuesto": {
							"dte:NombreCorto": item.impuestos.nombreCorto,
							"dte:CodigoUnidadGravable": item.impuestos.codigoUnidadGravable,
							"dte:MontoGravable": item.impuestos.montoGravable,
							"dte:MontoImpuesto": item.impuestos.montoImpuesto
						}
					},
					"dte:Total": item.total
				});
			}

			const builder = new xml2js.Builder();
			const xml = builder.buildObject(obj);
			
			let firmaDocumento = await Main.firma(xml);
			if(firmaDocumento.status === true){
				let registraDocumento = await Main.registra(unescape(firmaDocumento.data.FirmaDocumentoResponse.xml_dte));
				if(registraDocumento.status === true){
					let xmlResponse = registraDocumento.data.RegistraDocumentoXMLResponse.xml_dte;
					let result = await xml2js.parseStringPromise(xmlResponse, { mergeAttrs: true, attrkey: "attr" });
					return {
						status: true,
						message: 'documento procesador correctamente',
						data: {
							response: result,
							tipo_respuesta: registraDocumento.data.RegistraDocumentoXMLResponse.tipo_respuesta,
							uuid: registraDocumento.data.RegistraDocumentoXMLResponse.uuid
						}
					}
				}
				else{
					return {
						status: true,
						message: 'error al registrar documento',
						data: registraDocumento
					}
				}
			}else{
				return {
					status: true,
					message: 'error al firmar documento',
					data: firmaDocumento
				}
			}
			
		}

		// proceso de nota de credito
		static async notaCredito(req, res){
			const obj = {
				'dte:GTDocumento': {
					$:{
						'xmlns:dte': "http://www.sat.gob.gt/dte/fel/0.2.0",
						'xmlns:xd': "http://www.w3.org/2000/09/xmldsig#",
						'Version': "0.1"		
					},
					'dte:SAT': {
						$:{
							'ClaseDocumento': "dte"
						},
						'dte:DTE': {
							$:{
								'ID': "DatosCertificados"
							},
							'dte:DatosEmision':{
								$: {
									'ID': "DatosEmision"
								},
								'dte:DatosGenerales': {
									$: {
										'CodigoMoneda': "GTQ",
										'FechaHoraEmision': req.body.datosGenerales.fechaEmision,
										'Tipo': "NCRE"
									}
								},
								'dte:Emisor': {
									$: {
										'AfiliacionIVA': req.body.emisor.afiliacionIVA,
										'CodigoEstablecimiento': req.body.emisor.codigoEstablecimiento,
										'NITEmisor': req.body.emisor.nit,
										'NombreComercial': req.body.emisor.NombreComercial,
										'NombreEmisor': req.body.emisor.nombreEmisor
									},
									'dte:DireccionEmisor': {
										'dte:Direccion': req.body.emisor.direccionEmisor.direccion,
										'dte:CodigoPostal': req.body.emisor.direccionEmisor.codigoPostal,
										'dte:Municipio': req.body.emisor.direccionEmisor.municipio,
										'dte:Departamento': req.body.emisor.direccionEmisor.departamento,
										'dte:Pais': req.body.emisor.direccionEmisor.pais
									}
								},
								'dte:Receptor': {
									$: {
										'CorreoReceptor': req.body.receptor.correoReceptor,
										'IDReceptor': req.body.receptor.idReceptor,
										'NombreReceptor': req.body.receptor.nombreReceptor
									},
									'dte:DireccionReceptor': {
										'dte:Direccion': req.body.receptor.direccionReceptor.direccion,
										'dte:CodigoPostal': req.body.receptor.direccionReceptor.codigoPostal,
										'dte:Municipio': req.body.receptor.direccionReceptor.municipio,
										'dte:Departamento': req.body.receptor.direccionReceptor.departamento,
										'dte:Pais': req.body.receptor.direccionReceptor.pais
									}
								},
								'dte:Items': {
									'dte:Item':[]
								},
								'dte:Totales': {
									'dte:TotalImpuestos': {
										'dte:TotalImpuesto': {
											$: {
												'NombreCorto': req.body.totales.totalImpuestos.nombreCorto,
												'TotalMontoImpuesto': req.body.totales.totalImpuestos.totalMontoImpuesto
											}
										}
									},
									'dte:GranTotal': req.body.totales.granTotal
								},
								'dte:Complementos': {
									'dte:Complemento': {
										$: {
											'IDComplemento': "1",
											'NombreComplemento': "NOTA CREDITO",
											'URIComplemento': "http://www.sat.gob.gt/face2/ComplementoReferenciaNota/0.1.0"
										},
										'cno:ReferenciasNota': { 
											$: {
												'xmlns:cno': "http://www.sat.gob.gt/face2/ComplementoReferenciaNota/0.1.0",
												'FechaEmisionDocumentoOrigen': req.body.complementos.fechaEmisionDocumentoOrigen,
												'MotivoAjuste': req.body.complementos.motivoAjuste,
												'NumeroAutorizacionDocumentoOrigen': req.body.complementos.numeroAutorizacionDocumentoOrigen,
												'Version': "1"
											}
										}
									}
								}
							}
						}
					}
				}
			};

			var arrItems = obj['dte:GTDocumento']['dte:SAT']['dte:DTE']['dte:DatosEmision']['dte:Items']['dte:Item'] = [];
			
			for(var key in req.body.items){
				let item = req.body.items[key];
				arrItems.push({
					$: {
						"BienOServicio": item.bienOServicio,
						"NumeroLinea": key
					},
					"dte:Cantidad": item.cantidad,
					"dte:UnidadMedida": item.unidadMedida,
					"dte:Descripcion": item.descripcion,
					"dte:PrecioUnitario": item.precioUnitario,
					"dte:Precio": item.precio,
					"dte:Descuento": item.descuento,
					"dte:Impuestos": {
						"dte:Impuesto": {
							"dte:NombreCorto": item.impuestos.nombreCorto,
							"dte:CodigoUnidadGravable": item.impuestos.codigoUnidadGravable,
							"dte:MontoGravable": item.impuestos.montoGravable,
							"dte:MontoImpuesto": item.impuestos.montoImpuesto
						}
					},
					"dte:Total": item.total
				});
			}

			const builder = new xml2js.Builder();
			const xml = builder.buildObject(obj);
			
			let firmaDocumento = await Main.firma(xml);
			if(firmaDocumento.status === true){
				let registraDocumento = await Main.registra(unescape(firmaDocumento.data.FirmaDocumentoResponse.xml_dte));
				if(registraDocumento.status === true){
					let xmlResponse = registraDocumento.data.RegistraDocumentoXMLResponse.xml_dte;
					let result = await xml2js.parseStringPromise(xmlResponse, { mergeAttrs: true, attrkey: "attr" });
					return {
						status: true,
						message: 'documento procesador correctamente',
						data: {
							response: result,
							tipo_respuesta: registraDocumento.data.RegistraDocumentoXMLResponse.tipo_respuesta,
							uuid: registraDocumento.data.RegistraDocumentoXMLResponse.uuid
						}
					}
				}
				else{
					return {
						status: true,
						message: 'error al registrar documento',
						data: registraDocumento
					}
				}
			}else{
				return {
					status: true,
					message: 'error al firmar documento',
					data: firmaDocumento
				}
			}
		}

		// proceso de nota de abono
		static async notaAbono(req, res){
			const obj = {
				'dte:GTDocumento': {
					$:{
						'xmlns:dte': "http://www.sat.gob.gt/dte/fel/0.2.0",
						'xmlns:xd': "http://www.w3.org/2000/09/xmldsig#",
						'Version': "0.1"		
					},
					'dte:SAT': {
						$:{
							'ClaseDocumento': "dte"
						},
						'dte:DTE': {
							$:{
								'ID': "DatosCertificados"
							},
							'dte:DatosEmision':{
								$: {
									'ID': "DatosEmision"
								},
								'dte:DatosGenerales': {
									$: {
										'CodigoMoneda': "GTQ",
										'FechaHoraEmision': req.body.datosGenerales.fechaEmision,
										'Tipo': "NABN"
									}
								},
								'dte:Emisor': {
									$: {
										'AfiliacionIVA': req.body.emisor.afiliacionIVA,
										'CodigoEstablecimiento': req.body.emisor.codigoEstablecimiento,
										'NITEmisor': req.body.emisor.nit,
										'NombreComercial': req.body.emisor.NombreComercial,
										'NombreEmisor': req.body.emisor.nombreEmisor
									},
									'dte:DireccionEmisor': {
										'dte:Direccion': req.body.emisor.direccionEmisor.direccion,
										'dte:CodigoPostal': req.body.emisor.direccionEmisor.codigoPostal,
										'dte:Municipio': req.body.emisor.direccionEmisor.municipio,
										'dte:Departamento': req.body.emisor.direccionEmisor.departamento,
										'dte:Pais': req.body.emisor.direccionEmisor.pais
									}
								},
								'dte:Receptor': {
									$: {
										'CorreoReceptor': req.body.receptor.correoReceptor,
										'IDReceptor': req.body.receptor.idReceptor,
										'NombreReceptor': req.body.receptor.nombreReceptor
									},
									'dte:DireccionReceptor': {
										'dte:Direccion': req.body.receptor.direccionReceptor.direccion,
										'dte:CodigoPostal': req.body.receptor.direccionReceptor.codigoPostal,
										'dte:Municipio': req.body.receptor.direccionReceptor.municipio,
										'dte:Departamento': req.body.receptor.direccionReceptor.departamento,
										'dte:Pais': req.body.receptor.direccionReceptor.pais
									}
								},
								'dte:Items': {
									'dte:Item':[]
								},
								'dte:Totales': {
									'dte:GranTotal': req.body.totales.granTotal
								}
							}
						}
					}
				}
			};

			var arrItems = obj['dte:GTDocumento']['dte:SAT']['dte:DTE']['dte:DatosEmision']['dte:Items']['dte:Item'] = [];
			
			for(var key in req.body.items){
				let item = req.body.items[key];
				arrItems.push({
					$: {
						"BienOServicio": item.bienOServicio,
						"NumeroLinea": key
					},
					"dte:Cantidad": item.cantidad,
					"dte:UnidadMedida": item.unidadMedida,
					"dte:Descripcion": item.descripcion,
					"dte:PrecioUnitario": item.precioUnitario,
					"dte:Precio": item.precio,
					"dte:Descuento": item.descuento,
					"dte:Total": item.total
				});
			}

			const builder = new xml2js.Builder();
			const xml = builder.buildObject(obj);
			
			let firmaDocumento = await Main.firma(xml);
			if(firmaDocumento.status === true){
				let registraDocumento = await Main.registra(unescape(firmaDocumento.data.FirmaDocumentoResponse.xml_dte));
				if(registraDocumento.status === true){
					let xmlResponse = registraDocumento.data.RegistraDocumentoXMLResponse.xml_dte;
					let result = await xml2js.parseStringPromise(xmlResponse, { mergeAttrs: true, attrkey: "attr" });
					return {
						status: true,
						message: 'documento procesador correctamente',
						data: {
							response: result,
							tipo_respuesta: registraDocumento.data.RegistraDocumentoXMLResponse.tipo_respuesta,
							uuid: registraDocumento.data.RegistraDocumentoXMLResponse.uuid
						}
					}
				}
				else{
					return {
						status: true,
						message: 'error al registrar documento',
						data: registraDocumento
					}
				}
			}else{
				return {
					status: true,
					message: 'error al firmar documento',
					data: firmaDocumento
				}
			}
		}

		// proceso de anulacion directa
		static async anulacion(req, res){
			let fechaHoraAnulacion = new Date();
			const obj = {
				'ns:GTAnulacionDocumento': {
					$:{
						'xmlns:ns': "http://www.sat.gob.gt/dte/fel/0.1.0",
						'xmlns:xd': "http://www.w3.org/2000/09/xmldsig#",
						'Version': "0.1"		
					},
					'ns:SAT': {
						'ns:AnulacionDTE': {
							$:{
								'ID': "DatosCertificados"
							},
							'ns:DatosGenerales': {
								$: {
									'ID': "DatosAnulacion",
									'NumeroDocumentoAAnular': req.body.datosGenerales.numeroDocumentoAnular,
									'NITEmisor': req.body.datosGenerales.nitEmisor,
									'IDReceptor': req.body.datosGenerales.idReceptor, 
									'FechaEmisionDocumentoAnular': req.body.datosGenerales.fechaEmisionDocumentoAnular,
									'FechaHoraAnulacion': fechaHoraAnulacion.toISOString(),
									'MotivoAnulacion': req.body.datosGenerales.motivoAnulacion
								}
							}
						}
					}
				}
			};
			
			const builder = new xml2js.Builder();
			const xml = builder.buildObject(obj);

			// console.log(xml);

			let firmaDocumento = await Main.firma(xml);
			if(firmaDocumento.status === true){

				let token = await Main.token();

				let xmlResult = await fetch('https://dev2.api.ifacere-fel.com/api/anularDocumentoXML', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/xml;charset=UTF-8',
						'Authorization': token.data.token
					},
					body: `<?xml version='1.0' encoding='UTF-8'?>
							<AnulaDocumentoXMLRequest id="B10DC019-A68E-4977-85A8-848F623C518C">
								<xml_dte><![CDATA[
									`+unescape(firmaDocumento.data.FirmaDocumentoResponse.xml_dte)+`
								]]></xml_dte>
							</AnulaDocumentoXMLRequest>`
				}).then(response => response.text());

				let xmlAnula = await xml2js.parseStringPromise(xmlResult, { mergeAttrs: false, attrkey: "attr" });
				if(xmlAnula.AnulaDocumentoXMLResponse.tipo_respuesta == 1){
					return {
						status: false,
						message: 'error al anular documento',
						data: xmlAnula
					};
				}else{
					let xmlResponse = xmlAnula.AnulaDocumentoXMLResponse.xml_dte;
					let result = await xml2js.parseStringPromise(xmlResponse, { mergeAttrs: true, attrkey: "attr" });
					return {
						status: true,
						message: 'documento anulado',
						data: {
							response: result,
							tipo_respuesta: xmlAnula.AnulaDocumentoXMLResponse.tipo_respuesta,
							uuid: xmlAnula.AnulaDocumentoXMLResponse.uuid
						}
					};
				}
			}else{
				return {
					status: true,
					message: 'error al firmar documento',
					data: firmaDocumento
				}
			}
		}
		
	};
	return Main;
};