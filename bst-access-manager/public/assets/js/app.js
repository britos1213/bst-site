
document.addEventListener('click', function(e){
  const a=e.target.closest('a.disabled');
  if(a) e.preventDefault();
});
