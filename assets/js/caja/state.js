// Estado global y referencias al DOM
export const els = {
  // listado principal
  tbody:       document.querySelector('#tablaCajas tbody') || document.querySelector('#tbl_cajas tbody'),
  badgeFecha:  document.querySelector('#badgeFecha'),
  // KPIs
  kpiAbiertas: document.querySelector('#kpiAbiertas'),
  kpiPrecortes:document.querySelector('#kpiPrecortes'),
  kpiConcil:   document.querySelector('#kpiConcil'),
  kpiDifProm:  document.querySelector('#kpiDifProm'),

  // wizard
  modal:              document.getElementById('czModalPrecorte'),
  stepBar:            document.getElementById('czStepBar'),
  step1:              document.getElementById('czStep1'),
  step2:              document.getElementById('czStep2'),
  step3:              document.getElementById('czStep3'),

  // paso 1
  tablaDenomsBody:    document.querySelector('#czTablaDenoms tbody'),
  precorteTotal:      document.getElementById('czPrecorteTotal'),
  chipFondo:          document.getElementById('czChipFondo'),
  efEsperadoInfo:     document.getElementById('czEfectivoEsperado'),
  declCredito:        document.getElementById('czDeclCardCredito'),
  declDebito:         document.getElementById('czDeclCardDebito'),
  declTransfer:       document.getElementById('czDeclTransfer'),
  notasPaso1:         document.getElementById('czNotes'),
  btnGuardarPrecorte: document.getElementById('czBtnGuardarPrecorte'),
  btnContinuarConc:   document.getElementById('czBtnContinuarConciliacion'),
  inputPrecorteId:    document.getElementById('cz_precorte_id'),

  // paso 2
  bannerFaltaCorte:   document.getElementById('czBannerFaltaCorte'),
  btnSincronizarPOS:  document.getElementById('czBtnSincronizarPOS'),
  concGrid:           document.getElementById('czConciliacionGrid'),
  concNotas:          document.getElementById('czConciliacionNotas'),
  concNotasLabel:     document.getElementById('czConciliacionNotasLabel'),
  btnIrPostcorte:     document.getElementById('czBtnIrPostcorte'),

  // paso 3
  corteResumen:       document.getElementById('czCorteResumen'),
  depFolio:           document.getElementById('czDepFolio'),
  depCuenta:          document.getElementById('czDepCuenta'),
  depEvidencia:       document.getElementById('czDepEvidencia'),
  notasCierre:        document.getElementById('czNotasCierre'),
  btnCerrarSesion:    document.getElementById('czBtnCerrarSesion'),
};

export const state = {
  date: null,
  data: [],
  sesion: { store:0, terminal:0, user:0, bdate:'', opening:0 },
  precorteId: null,
  denoms: new Map(),
  decl: { credito:0, debito:0, transfer:0 },
  pasoGuardado: false,
  step: 1,
};
