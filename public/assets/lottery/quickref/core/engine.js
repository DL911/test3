(function(root){
const defs={one:['一码',3.3],onepos:['一码定位',9],two:['两码',16.3],pair:['两码对子',32],twopos:['两码定位',90],twog3:['两码组三',150],pairall:['对子全包',3.2],g3all:['组三全包',3.3],g6all:['组六全包',1.25],direct:['三码直选',900],g6_3:['三码组选六',150],g3_3:['三码组三',300],bomb:['具体豹子',900],bomball:['豹子全包',90],sum:['和值',0],sumodd:['和值单',1.8],sumeven:['和值双',1.8],sumbig:['和值大',1.8],sumsmall:['和值小',1.8],posodd:['位置单',1.8],poseven:['位置双',1.8],posbig:['单位置大',1.8],possmall:['单位置小',1.8]};
[[4,37,25],[5,15,15],[6,7.5,10],[7,4.3,7.1],[8,2.6,5.3],[9,1.8,4.1]].forEach(([n,a,b])=>{defs['g6_'+n]=[n+'码组六',a];defs['g3_'+n]=[n+'码组三',b]});
const sums=[860,300,150,90,60,43,32,24,19,16,14,13,12,11.5];
const tokens=s=>s.trim().split(/[\s,，.。;；、/]+/).filter(Boolean);
const full=new Set(['pairall','g3all','g6all','bomball','sumodd','sumeven','sumbig','sumsmall','posodd','poseven','posbig','possmall']);
function gameForRecord(raw){
  const text=raw.replace(/\d{4}(?:年\d{1,2}月\d{1,2}日|[-/]\d{1,2}[-/]\d{1,2})\s+\d{1,2}:\d{2}(?::\d{2})?/g,' ');
  const markers=[...text.matchAll(/福彩3D|福彩|3[Dd]|体彩|排列三|排三|(?:^|[\s，,：:])(?:体|排|福)(?=\s|直选|直|组选|组|组三|组六|一码|两码|二码|和值|独|\d)/g)];
  if(markers.length){const games=new Set(markers.map(m=>/体彩|排列三|排三|体|排/.test(m[0])?'P3':'3D'));return games.size===1?[...games][0]:null}
  // The timestamp must occur in the record, never use the upload/current time.
  const times=[...raw.matchAll(/(?:^|\s)([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?(?=\s|$)/g)];
  const games=new Set(times.map(t=>Number(t[1])*60+Number(t[2])>=21*60+15?'P3':'3D'));
  return games.size>1?null:games.size?[...games][0]:'3D';
}
function validate(e){if(!defs[e.play])return '请选择已支持的玩法';if(!['3D','P3'].includes(e.game))return '彩种无效';if(!Number.isFinite(e.amount)||e.amount<=0||e.amount>100000000||Math.abs(e.amount*100-Math.round(e.amount*100))>1e-6)return '金额应为正数，最多两位小数';if(!Array.isArray(e.numbers)||!e.numbers.every(x=>typeof x==='string'))return '号码格式错误';if(!full.has(e.play)&&!e.numbers.length)return '请填写号码';for(const n of e.numbers){if(!/^\d+$/.test(n))return '号码只能包含数字';if(e.play==='sum'){if(Number(n)>27||n.length>2)return '和值范围是0至27';continue}const required={one:1,onepos:1,two:2,pair:2,twopos:2,twog3:2,direct:3,bomb:3}[e.play]||Number(e.play.slice(3));if(n.length!==required)return '号码位数与玩法不符';const unique=new Set(n).size;if(e.play==='bomb'&&unique!==1)return '豹子必须三个数字相同';if(e.play==='pair'&&unique!==1)return '对子必须两个数字相同';if((/^g[36]_/.test(e.play)||['two','twog3'].includes(e.play))&&unique!==n.length&&!(e.play==='g3_3'&&unique===2))return '该玩法请选择互不相同的数字（具体三码组三可保留一个对子）'}if(e.play==='onepos'&&!/^[012]$/.test(e.pos))return '请选择一个位置';if(e.play==='twopos'&&!/^(01|02|12)$/.test(e.pos))return '请选择两个位置';if(/^pos/.test(e.play)&&!(/^(0|1|2|01|02|12)$/.test(e.pos)))return '请选择位置';if(['posbig','possmall'].includes(e.play)&&e.pos.length!==1)return '两位置大小规则待确认，暂不支持';return ''}
function units(e){return full.has(e.play)?1:e.numbers.length}
function stake(e){return Math.round(e.amount*100)*units(e)/100}
// Correct the old erroneous default without overwriting later custom odds,
// stakes, other plays, or the immutable snapshots in saved group archives.
function correctBombAllRate(data){
  const rateChanged=data.rates?.bomball===9;let entriesChanged=0;
  const entries=(data.entries||[]).map(e=>{if(e.play==='bomball'&&e.rate===9){entriesChanged++;return {...e,rate:90}}return e});
  return {changed:rateChanged||entriesChanged>0,entriesChanged,db:{...data,rates:rateChanged?{...data.rates,bomball:90}:data.rates,entries}};
}
function matches(e,n,d){const a=d.split(''),u=new Set(a),bomb=u.size===1,g3=u.size===2,g6=u.size===3,p=e.play;if(p==='one')return a.includes(n);if(p==='onepos')return a[Number(e.pos)]===n;if(p==='two')return [...n].every(x=>a.includes(x));if(p==='pair')return (!bomb||e.includeBomb)&&a.filter(x=>x===n[0]).length>=2;if(p==='twopos')return [...e.pos].every((x,i)=>a[Number(x)]===n[i]);if(p==='twog3')return (g3||(bomb&&e.includeBomb))&&a.every(x=>n.includes(x));if(p==='direct'||p==='bomb')return n===d;if(/^g6_/.test(p))return g6&&a.every(x=>n.includes(x));if(p==='g3_3'&&new Set(n).size===2)return g3&&[...n].sort().join('')===[...d].sort().join('');if(/^g3_/.test(p))return (g3||(bomb&&e.includeBomb))&&a.every(x=>n.includes(x));if(p==='pairall')return g3||(bomb&&e.includeBomb);if(p==='g3all')return g3||(bomb&&e.includeBomb);if(p==='g6all')return g6;if(p==='bomball')return bomb;const sum=a.reduce((s,x)=>s+Number(x),0);if(p==='sum')return sum===Number(n);if(p==='sumodd')return sum%2===1;if(p==='sumeven')return sum%2===0;if(p==='sumbig')return sum>=14;if(p==='sumsmall')return sum<=13;const v=[...e.pos].reduce((s,x)=>s+Number(a[Number(x)]),0);return p==='posodd'?v%2===1:p==='poseven'?v%2===0:p==='posbig'?v>=5:p==='possmall'?v<=4:false}
function award(e,draw){if(!/^\d{3}$/.test(draw||''))return null;return (full.has(e.play)?['']:e.numbers).reduce((t,n)=>t+(matches(e,n,draw)?Math.round(e.amount*(e.play==='sum'?sums[Math.min(Number(n),27-Number(n))]:e.rate)*100):0),0)/100}
function parseLine(raw,fallback,rates){let text=raw.trim().replace(/\s+/g,' ').replace(/二码/g,'两码').replace(/独胆|独旦/g,'一码').replace(/＊/g,'*'),customer=fallback.trim(),game=gameForRecord(raw)||'3D',reason='',result=[];const name=text.match(/^([^\d：:\n]{1,24})[：:]\s*/);if(name){customer=name[1].trim();text=text.slice(name[0].length)}if(/体彩|排列三|排三|(?:^|\s)[体排](?=\s|直选|直|组选|组|组三|组六|\d)/.test(text)){game='P3';text=text.replace(/体彩|排列三|排三|(?:^|\s)[体排](?=\s|直选|直|组选|组|组三|组六|\d)/g,' ').trim()}text=text.replace(/福彩3D|福彩|3D|3d|(?:^|\s)福(?=\s|直选|直|组选|组|组三|组六|\d)/g,' ').trim();const make=(play,numbers,amount,pos='')=>{const e={customer,game,play,numbers,amount,pos,includeBomb:false,rate:rates[play],raw,status:'pending'};e.reason=validate(e);result.push(e)};
if(/撤|取消|不要|改|照旧|加单|同上|含豹|含炸/.test(text))reason='包含改单、上下文或特殊规则，请手动确认';
const each=text.match(/^(.*?)\s*各\s*(?:打\s*)?(\d+(?:\.\d+)?)\s*(?:倍|元|块)?\s*(直选|直|组选|组|组六|组三)?\s*$/);
if(each){const before=each[1],tail=each[3]||'';if((/直选|直/.test(before)&&/组/.test(tail))||(/组选|组三|组六|组/.test(before)&&/直/.test(tail)))reason='前后玩法不一致，请选择正确玩法';text=before+' '+tail+' '+each[2]}
if(!each&&/[\r\n]/.test(raw)&&raw.split(/\r?\n/).every(line=>!line.trim()||isNumberBlock(line)||isBlockHeader(line)))reason='多行号码缺少明确金额，请补充';
text=text.replace(/\s+打\s*(\d+(?:\.\d+)?)\s*(?:元|块|倍)\s*$/, ' $1');
const m=text.match(/^(.*?)\s*[,，\s]\s*(\d+(?:\.\d+)?)\s*(?:元|块)?\s*(?:(\+|防(?:对子|对|组三)?)\s*(\d+(?:\.\d+)?)\s*(?:元|块)?)?\s*$/);
if(!reason&&m){let head=m[1].trim(),amount=Number(m[2]),extra=m[4]===undefined?null:Number(m[4]),sep=m[3];let pos='';for(const [w,v] of [['百十','01'],['百个','02'],['十个','12'],['百位','0'],['十位','1'],['个位','2']])if(head.includes(w)){pos=v;head=head.replace(w,'');break}
if(/两码定位/.test(head)&&head.includes('*')){
  const masks=tokens(head.replace(/两码定位|定位/g,'').trim());
  if(extra!==null||!masks.length||masks.some(n=>!/^(?:\d\d\*|\d\*\d|\*\d\d)$/.test(n)))reason='星号两码定位格式或金额不明确，请补充';
  else {for(const mask of masks){const positions=[0,1,2].filter(i=>mask[i]!=='*');make('twopos',[positions.map(i=>mask[i]).join('')],amount,positions.join(''))}return result}
}
let play='';if(/炸弹|豹子/.test(head)){head=head.replace(/炸弹|豹子/g,'').trim();play=head?'bomb':'bomball';if(/^\d$/.test(head))head=head.repeat(3)}else if(/全包/.test(head)){play=/组六/.test(head)?'g6all':/组三/.test(head)?'g3all':/对子/.test(head)?'pairall':'';head=''}else if(/和值/.test(head)){head=head.replace('和值','').trim();if(/^[单双大小]$/.test(head)){play={单:'sumodd',双:'sumeven',大:'sumbig',小:'sumsmall'}[head];head=''}else play='sum'}else if(pos&&/^[单双大小]$/.test(head)){play={单:'posodd',双:'poseven',大:'posbig',小:'possmall'}[head];head=''}
let explicit=/直选|直/.test(head)?'direct':/组三|对子|防对|防组三/.test(head)?'g3':/组六|组选|组/.test(head)?'g6':'';head=head.replace(/直选|组选|组三|组六|防对子|防对|防组三|对子|直|组|一码定位|两码定位|定位|一码|两码/g,'').trim();const ns=tokens(head),len=ns[0]?.length;if(!play&&ns.length&&ns.every(n=>/^\d+$/.test(n)&&n.length===len)){if(pos)play=len===1?'onepos':len===2?'twopos':'';else if(len===1)play='one';else if(len===2)play=explicit==='g3'?'twog3':ns.every(n=>n[0]===n[1])?'pair':'two';else if(len===3&&ns.every(n=>new Set(n).size===1))play='bomb';else if(len===3&&explicit!=='direct'&&ns.every(n=>new Set(n).size===2))play='g3_3';else if(len>=3&&len<=9)play=explicit==='direct'?'direct':(explicit==='g3'?'g3_':'g6_')+len}
if(play){if(extra!==null){if(len===3&&!pos&&!explicit&&sep==='+'){make('direct',ns,amount);make('g6_3',ns,extra)}else if(len>=3&&len<=9&&!pos&&!explicit){make('g6_'+len,ns,amount);make('g3_'+len,ns,extra)}else reason='组合金额与玩法不明确，请手动拆分'}else make(play,full.has(play)?[]:ns,amount,pos)}else reason='未识别玩法，请手动选择'}else if(!reason)reason='写法尚未支持，请选择玩法、号码和金额';
if(reason||!result.length)return [{customer,game,play:'',numbers:[],amount:0,pos:'',includeBomb:false,raw,status:'pending',reason:reason||'需要确认'}];return result}
function prepareAdd(raw,rates){
  const entries=parseLine(raw,'',rates);
  const issue=entries.map(e=>e.reason||validate(e)||(!Number.isFinite(e.rate)||e.rate<=0?'赔率无效':'')).find(Boolean);
  // A line may split into two plays. If either is ambiguous, keep the entire
  // line out of totals, rather than silently adding only half of the stake.
  return entries.map(e=>({...e,status:issue?'pending':'confirmed',reason:issue?(e.reason||'同一条原文有未明确的投注：'+issue):''}));
}
function stripBlockLabels(text){return text.trim().replace(/二码/g,'两码').replace(/独胆|独旦/g,'一码').replace(/＊/g,'*').replace(/^(?:福彩3D|福彩|3[Dd]|福|体彩|排列三|排三|体|排)\s*/,'').replace(/^(?:一码定位|两码定位|一码|两码|和值|直选|组选|组三|组六|直|组)\s*/,'').trim()}
function isBlockHeader(text){return !!text.trim()&&!stripBlockLabels(text)}
function isNumberBlock(text){const rest=stripBlockLabels(text);if(!/^[\d\s,，.。;；、/]+$/.test(rest))return false;const ns=tokens(rest);return ns.length>0&&ns.every(n=>/^\d{3,9}$/.test(n)&&n.length===ns[0].length)}
function isPositionBlock(text){return /^(?:百位|十位|个位)\s*\d$/.test(stripBlockLabels(text))}
function isMaskBlock(text){const ns=tokens(stripBlockLabels(text));return ns.length>0&&ns.every(n=>/^(?:\d\d\*|\d\*\d|\*\d\d)$/.test(n))}
function splitRecords(raw,known){
  const records=[],buffer=[];
  const defaults=Object.fromEntries(Object.entries(defs).map(([k,v])=>[k,v[1]]));
  const valid=s=>{if(typeof known==='function'&&known(s))return true;const entries=parseLine(s,'',defaults);return entries.length>0&&entries.every(e=>!e.reason&&!validate(e))};
  const flush=()=>{if(buffer.length){records.push(buffer.join('\n'));buffer.length=0}};
  for(const source of raw.split(/\r?\n/)){
    const line=source.trim();if(!line)continue;
    if(isBlockHeader(line)){
      // Consecutive header lines (e.g. 排 / 一码定位) describe one ticket.
      // A new header after numbers starts another ticket, never overwrites it.
      if(buffer.some(x=>!isBlockHeader(x)))flush();
      buffer.push(line);continue;
    }
    const continuation=isNumberBlock(line)||isPositionBlock(line)||isMaskBlock(line);
    const amountLine=/^各\s*(?:打\s*)?\d/.test(line)||/^打\s*\d+(?:\.\d+)?\s*(?:元|块|倍)\s*$/.test(line);
    if(buffer.length){
      const combined=[...buffer,line].join('\n');
      if(!continuation&&valid(combined)){buffer.push(line);flush();continue}
      if(continuation||amountLine){buffer.push(line);if(amountLine)flush();continue}
      // Preserve a complete independent ticket when preceding text is unclear.
      if(valid(line)){flush();records.push(line);continue}
      buffer.push(line);continue;
    }
    if(continuation){buffer.push(line);continue}
    if(valid(line)){records.push(line);continue}
    buffer.push(line);
  }
  flush();return records;
}

const api={defs,sums,tokens,validate,units,stake,award,parseLine,prepareAdd,splitRecords,gameForRecord,correctBombAllRate};root.LedgerEngine=api;if(typeof module!=='undefined')module.exports=api;
})(globalThis);
