import fs from 'node:fs';
import vm from 'node:vm';
import {spawnSync} from 'node:child_process';
import path from 'node:path';

const root=path.resolve(import.meta.dirname,'../../public/assets/lottery/quickref');
for(const file of ['core/engine.js','core/learned.js','core/model-result.js','core/knowledge.js','core/stream.js','module.js','offline.js']){
  vm.runInThisContext(fs.readFileSync(path.join(root,file),'utf8'),{filename:file});
}
const S=globalThis.LotteryRecognitionOdds,settings=S.defaultSettings();
const examples={
  one:'5',onepos:'5',two:'12',pair:'11',twopos:'56',twog3:'12',
  pairall:'',g3all:'',g6all:'',direct:'518',g6_3:'518',g3_3:'007',
  bomb:'111',bomball:'',sumodd:'',sumeven:'',sumbig:'',sumsmall:'',
  posodd:'',poseven:'',posbig:'',possmall:''
};
for(let n=4;n<=9;n++){examples['g6_'+n]='012345678'.slice(0,n);examples['g3_'+n]='012345678'.slice(0,n)}
for(let n=0;n<=13;n++)examples['sum_'+n]=String(n);
const draws=['000','007','070','077','111','123','185','518','581','564','654','999'];
const tests=[],expected=[];
for(const [p,number] of Object.entries(examples)){
  const play=p.startsWith('sum_')?'sum':p;
  const rate=play==='sum'?settings.sumRates[Number(p.slice(4))]:settings.rates[p];
  const pos=p==='twopos'?'01':p==='onepos'||p.startsWith('pos')?'0':'';
  for(const draw of draws){
    const ref={play,numbers:number?[number]:[],pos,amount:2,rate,status:'confirmed',game:'3D',includeBomb:false};
    const item={play:p,number,pos,amount:'2.00',odds:rate,include_bomb:0};
    tests.push({item,draw});expected.push(S.calculateAward(ref,draw,{settings}));
  }
}
for(const p of ['pair','twog3','pairall','g3all','g3_3','g3_4']){
  const number=examples[p],rate=settings.rates[p];
  for(const draw of ['111','222','007']){
    const ref={play:p,numbers:number?[number]:[],pos:'',amount:2,rate,status:'confirmed',game:'3D',includeBomb:true};
    const item={play:p,number,pos:'',amount:'2.00',odds:rate,include_bomb:1};
    tests.push({item,draw});expected.push(S.calculateAward(ref,draw,{settings}));
  }
}
const php=spawnSync('php',[path.join(import.meta.dirname,'quick_bet_award_cli.php')],{input:JSON.stringify(tests),encoding:'utf8'});
if(php.status!==0)throw Error(php.stderr||php.stdout);
const actual=JSON.parse(php.stdout);
for(let i=0;i<tests.length;i++)if(Math.abs(actual[i]-expected[i])>0.001)throw Error(JSON.stringify({case:tests[i],expected:expected[i],actual:actual[i]}));
console.log('quick bet parity passed: '+tests.length+' cases');
