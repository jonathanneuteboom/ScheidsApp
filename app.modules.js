angular.module('ScheidsApp', [
  'availability',
  'schedule',
  'make-schedule',
  'dataexporteren',
  'tellers',
  'zaalwacht'
]).factory('progress', function (LxProgressService){
  var progress = {};
  
  progress.show = function(){
    LxProgressService.circular.show('#5fa2db', '#progress');
  };

  progress.hide = function(){
    LxProgressService.circular.hide();
  };
  
  return progress;
});
