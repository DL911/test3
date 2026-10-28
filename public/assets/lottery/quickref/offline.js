(function(root){
'use strict';
const S=root.LotteryRecognitionOdds,E=root.LedgerEngine,L=root.LedgerLearning;
const key=s=>s.trim().replace(/\s+/g,' ').replace(/，/g,',');
const markers=/福彩3D|福彩|3[Dd]|体彩|排列三|排三|福|体|排/g;
const unsafe=/撤|取消|不要|改成|照旧|加单|同上/;
const numericTokens=s=>s.trim().split(/[\s,，.。;；、/]+/).filter(Boolean);
const dateLine=/^\d{4}(?:年\d{1,2}月\d{1,2}日|[-/]\d{1,2}[-/]\d{1,2})\s*\d{1,2}[:：]\d{2}(?:[:：]\d{2})?$/;
const senderLine=s=>/^[\u4e00-\u9fffA-Za-z_]{1,24}$/.test(s)&&!/福彩|体彩|排列|排三|直选|组选|组三|组六|和值|定位|全包|^福$|^体$|^排$|^直$|^组$/.test(s);
function chatMessages(raw){
  const lines=[];let offset=0;
  for(const line of raw.split('\n')){lines.push({text:line.trim(),offset});offset+=line.length+1}
  const starts=[];
  for(let i=0;i<lines.length;i++)if(dateLine.test(lines[i].text)){
    let previous=i-1;while(previous>=0&&!lines[previous].text)previous--;
    starts.push(previous>=0&&senderLine(lines[previous].text)?lines[previous].offset:lines[i].offset);
  }
  if(starts.length<2)return [raw];
  if(raw.slice(0,starts[0]).trim())starts.unshift(0);
  return starts.map((start,i)=>raw.slice(start,starts[i+1]??raw.length).trim()).filter(Boolean);
}
function metadata(raw){
  const lines=raw.split('\n');let count=0;
  if(dateLine.test(lines[0]?.trim()||''))count=1;
  else if(senderLine(lines[0]?.trim()||'')&&dateLine.test(lines[1]?.trim()||''))count=2;
  const length=lines.slice(0,count).reduce((n,line)=>n+line.length+1,0);
  return {prefix:raw.slice(0,length),body:raw.slice(length)};
}
const moneyPattern='(?:\\d+(?:\\.\\d+)?|[零〇一二两三四五六七八九十百千万]+)';
function moneyValue(value){
  if(/^\d+(?:\.\d+)?$/.test(value))return Number(value);
  if(!/^[零〇一二两三四五六七八九十百千万]+$/.test(value))return NaN;
  // Colloquial 一百二 is ambiguous between 102 and 120; require explicit 零/十.
  if(/[百千万][一二两三四五六七八九]$/.test(value))return NaN;
  const digits={零:0,〇:0,一:1,二:2,两:2,三:3,四:4,五:5,六:6,七:7,八:8,九:9},units={十:10,百:100,千:1000,万:10000};
  let total=0,section=0,number=0;
  for(const c of value){if(Object.hasOwn(digits,c)){number=digits[c];continue}const unit=units[c];if(unit===10000){total+=(section+number)*unit;section=0;number=0}else{section+=(number||1)*unit;number=0}}
  const result=total+section+number;
  function sectionName(n){let text='',zero=false;for(const [unit,label]of [[1000,'千'],[100,'百'],[10,'十'],[1,'']]){const digit=Math.floor(n/unit)%10;if(digit){if(zero)text+='零';text+='零一二三四五六七八九'[digit]+label;zero=false}else if(text&&n%unit)zero=true}return text}
  const high=Math.floor(result/10000),low=result%10000;
  const canonical=(high?sectionName(high)+'万'+(low?(low<1000?'零':'')+sectionName(low):''):sectionName(low)||'零').replace(/^一十/,'十');
  return canonical===value.replace(/两/g,'二').replace(/〇/g,'零').replace(/^一十/,'十')?result:NaN;
}
function clean(raw){
  const lines=raw.trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  const date=dateLine;
  if(lines.length>1&&date.test(lines[1])&&/^[\u4e00-\u9fffA-Za-z_]{1,24}$/.test(lines[0]))lines.shift();
  if(lines.length&&date.test(lines[0]))lines.shift();
  return lines.join('\n').replace(/＊/g,'*').replace(/[➕＋]/g,'+').replace(/[…—－]+/g,' ').replace(/二码/g,'两码');
}
function candidate(raw,entries,settings){
  return S.normalizeModelResult({segments:[{raw,question:'',entries}]},raw,{settings}).map(e=>({...e,source:'offline'}));
}
function pending(raw,reason='这段写法暂未收录。请写出明确的号码、玩法和金额，或选择玩法补充。',game='3D'){
  return [{raw,game,play:'',numbers:[],amount:0,pos:'',includeBomb:false,rate:0,status:'pending',reason,source:'offline',suggestedEntries:[]}];
}
function groupPlay(number,kind='group'){
  if(kind==='direct')return 'direct';
  if(kind==='g3')return 'g3_'+number.length;
  if(new Set(number).size===1&&number.length===3)return 'bomb';
  if(new Set(number).size===2&&number.length===3)return 'g3_3';
  return 'g6_'+number.length;
}
function entry(game,play,numbers,amount,pos=''){return {game,play,numbers,amount:Number(amount),pos,includeBomb:false}}
function parseBlock(raw,settings,memories,game){
  const remembered=[...memories].reverse().find(r=>r.key===key(raw));
  if(remembered)return candidate(raw,(remembered.entries||[remembered.entry]).map(e=>({...e,game})),settings).map(e=>({...e,learned:true}));
  if(unsafe.test(raw))return pending(raw,'这条内容依赖前文或涉及改单，请补充完整的投注内容。',game);
  let text=clean(raw);
  const correction=L.knownCorrection(text,settings.rates);
  if(correction)return candidate(raw,correction.map(e=>({...e,game})),settings);
  text=text.replace(/[＝=]\s*\d+(?:\.\d+)?\s*(?:元|块)?\s*$/,'').trim()
    .replace(/(直|组)\s*[。.,，：:]\s*(?=[\d零〇一二两三四五六七八九十百千万])/g,'$1 ')
    .replace(/([\d零〇一二两三四五六七八九十百千万])\s*(?:元|块)?\s*[。.,，;；]+\s*(?=(?:直选|直|组选|组))/g,'$1 ');
  const body=text.replace(markers,'').trim().replace(/^[：:]+\s*/,'').replace(/\s+/g,' ');
  const numberList='([\\d\\s,，.。;；、/]+?)';
  const amount='('+moneyPattern+')\\s*(?:元|块|倍)?';
  const labelled=body.match(new RegExp('^'+numberList+'\\s*(?:各\\s*)?(?:打\\s*)?(?:直选|直)\\s*'+amount+'\\s*\\+?\\s*(?:组选|组)\\s*'+amount+'\\s*$'));
  const amountFirst=body.match(new RegExp('^'+numberList+'(?:\\s+(?:各\\s*)?(?:打\\s*)?|\\s*(?:各打|各|打)\\s*)'+amount+'\\s*(?:直选|直)\\s*\\+?\\s*'+amount+'\\s*(?:组选|组)\\s*$'));
  if(labelled||amountFirst){
    const m=labelled||amountFirst,ns=numericTokens(m[1]),direct=moneyValue(m[2]),group=moneyValue(m[3]);
    if(ns.length&&ns.every(n=>/^\d{3}$/.test(n))&&Number.isFinite(direct)&&Number.isFinite(group)){
      return candidate(raw,ns.flatMap(n=>[entry(game,'direct',[n],direct),entry(game,groupPlay(n),[n],group)]),settings);
    }
    return pending(raw,'直选、组选的号码或金额仍不明确，请补充完整。',game);
  }
  const sumCondition=body.match(new RegExp('^(?:和值([单双大小])|([单双大小])和值)\\s*(?:打\\s*)?'+amount+'\\s*$'));
  if(sumCondition){const play={单:'sumodd',双:'sumeven',大:'sumbig',小:'sumsmall'}[sumCondition[1]||sumCondition[2]];return candidate(raw,[entry(game,play,[],moneyValue(sumCondition[3]))],settings)}
  // A digit followed by 打 is a number and a stake, never a multiplier.
  const hit=text.replace(markers,'').trim().match(/^(\d{1,9})\s*打\s*(\d+(?:\.\d+)?)\s*(?:元|块|倍)?$/);
  if(hit){const n=hit[1],play=n.length===1?'one':n.length===2?(n[0]===n[1]?'pair':'two'):groupPlay(n);return candidate(raw,[entry(game,play,[n],hit[2])],settings)}
  text=text.replace(/^(\s*(?:(?:福彩|体彩|福|体|排)\s*)?\d{3,9})[.。]+(?=\d+\+)/,'$1 ');
  // Explicit 直/组 amounts and the confirmed 三码 A+B convention.
  const bare=text.replace(markers,'').trim().replace(/^[：:]+\s*/,'').replace(/\s+/g,' ');
  const explicit=bare.match(/^([\d\s,，.。;；、/]+)\s*(?:直选|直)\s*(\d+(?:\.\d+)?)\s*(?:元|块)?\s*(?:组选|组)\s*(\d+(?:\.\d+)?)\s*(?:元|块)?$/);
  const dual=bare.match(/^([\d\s,，.。;；、/]+?)\s*(?:各\s*(?:打\s*)?|[,，\s])\s*(\d+(?:\.\d+)?)\s*\+\s*(\d+(?:\.\d+)?)\s*(?:元|块)?$/);
  const directOnly=bare.match(/^([\d\s,，.。;；、/]+)\s*(?:直选|直)\s*(\d+(?:\.\d+)?)\s*(?:元|块|倍)?$/);
  if(directOnly)return candidate(raw,numericTokens(directOnly[1]).map(n=>entry(game,'direct',[n],directOnly[2])),settings);
  if(explicit||dual){
    const m=explicit||dual,ns=numericTokens(m[1]);
    if(ns.length&&ns.every(n=>/^\d{3,9}$/.test(n)&&n.length===ns[0].length)){
      const rows=[];for(const n of ns){rows.push(entry(game,explicit||n.length===3?'direct':groupPlay(n),[n],m[2]));rows.push(entry(game,n.length===3?groupPlay(n):groupPlay(n,'g3'),[n],m[3]))}
      return candidate(raw,rows,settings);
    }
  }
  const normal=E.prepareAdd(text,settings.rates);
  if(normal.length&&normal.every(e=>e.status==='confirmed'))return candidate(raw,normal.map(e=>({...e,game})),settings);
  return pending(raw,normal.find(e=>e.reason)?.reason,game);
}
function checkDeclared(raw,rows){
  const totals=[...raw.matchAll(/(?:合计|总计|共计|计|[＝=])\s*(\d+(?:\.\d+)?)\s*(?:元|块)?/g)];
  if(totals.length===1&&rows.every(e=>e.status==='confirmed')){
    const total=rows.reduce((sum,e)=>sum+Math.round(S.calculateStake(e)*100),0)/100,declared=Number(totals[0][1]);
    if(Math.abs(total-declared)>0.001){const waiting=pending(raw,'原文写合计'+declared+'元，但逐项金额合计'+total+'元。请核对；若逐项金额正确，可以采用下面的解释。',rows[0].game);waiting[0].suggestedEntries=rows;return waiting}
  }
  return rows;
}
function recognizeOffline(raw,{game='3D',settings=S.defaultSettings(),memories=[]}={}){
  settings=S.validateSettings(settings);memories=S.validateMemories(memories);if(!['3D','P3'].includes(game))throw Error('游戏页面配置无效');
  if(typeof raw!=='string'||!raw.trim()||raw.length>10000)throw Error('请提供一万字以内的投注内容');
  raw=raw.trim();
  const exact=[...memories].reverse().find(r=>r.key===key(raw));
  if(exact)return parseBlock(raw,settings,memories,game);
  const messages=chatMessages(raw);
  if(messages.length>1){
    const entries=messages.flatMap(message=>recognizeOffline(message,{game,settings,memories}));
    if(entries.length>1000)throw Error('注单过多，请分批填写');
    return entries;
  }
  // Learned conservative templates support changed numbers and amounts.
  const learned=L.recognize(raw,settings.rates,memories);
  if(learned.length&&learned.every(e=>e.status==='confirmed'&&e.learned))return candidate(raw,learned.map(e=>({...e,game})),settings).map(e=>({...e,learned:true}));
  const whole=parseBlock(raw,settings,memories,game);if(whole.every(e=>e.status==='confirmed'))return checkDeclared(raw,whole);
  const source=metadata(raw);
  const records=E.splitRecords(source.body,record=>parseBlock(record,settings,memories,game).every(e=>e.status==='confirmed')).map((record,i)=>i===0?source.prefix+record:record);
  const rows=records.flatMap(record=>checkDeclared(record,parseBlock(record,settings,memories,game)));
  if(rows.length>1000)throw Error('注单过多，请分批填写');
  return rows;
}
function clarifyOffline(raw,answer,proposal=[],options={}){
  const settings=S.validateSettings(options.settings||S.defaultSettings()),game=options.game||'3D';if(!['3D','P3'].includes(game))throw Error('游戏页面配置无效');
  if(typeof answer!=='string'||!answer.trim()||answer.length>2000)throw Error('请填写两千字以内的解释');
  answer=answer.trim();
  if(/^(对|是的|没错|正确)[。！!\s]*$/.test(answer)&&proposal.length)return candidate(raw,proposal.map(e=>({...e,game})),settings);
  let explanation=answer.replace(/^(?:不是|不对)[，,：:\s]*/,'').replace(/^(?:这是|就是|意思是|按)\s*/,'').replace(/的意思[。！!]?$/,'').trim();
  // An explanation may state just the plays and amounts for one existing pool.
  const body=clean(raw).replace(markers,'').replace(/---\s*\d+(?:-\d+){1,2}/g,'');
  const originalNumbers=[...body.matchAll(/(?<!\d)\d{3,9}(?!\d)/g)].map(m=>m[0]);
  const defense=explanation.match(/^(?:整组)?组六\s*(\d+(?:\.\d+)?)\s*(?:元|块)?[，,、\s]+(?:整组)?(?:组三|防对子|防对)\s*(\d+(?:\.\d+)?)\s*(?:元|块)?[。！!]?$/);
  if(defense&&originalNumbers.length===1){const n=originalNumbers[0];return candidate(raw,[entry(game,groupPlay(n),[n],defense[1]),entry(game,groupPlay(n,'g3'),[n],defense[2])],settings)}
  const rows=recognizeOffline(explanation,{game,settings,memories:[]});
  if(rows.some(e=>e.status!=='confirmed'))throw Error('这段解释还不能离线确定。请写成完整格式，例如“12345，50+10”，或点“选择玩法补充”；可为同一条原文补充多个玩法。');
  return candidate(raw,rows.map(e=>({...e,game})),settings);
}
function learnConfirmed(memories,rows,{skip=[]}={}){
  let result=S.validateMemories(memories);const skipped=[];
  const groups=new Map();for(const e of rows){if(e.status!=='confirmed')continue;const list=groups.get(e.raw)||[];list.push(e);groups.set(e.raw,list)}
  for(const [raw,entries]of groups){
    if(skip.includes(raw)||raw==='手动录入')continue;
    try{result=S.rememberExplanation(result,raw,entries,'客户已核对并确认的离线识别')}catch{skipped.push(raw)}
  }
  return {memories:result,skipped};
}
Object.assign(S,{recognizeOffline,clarifyOffline,learnConfirmed});
})(globalThis);
