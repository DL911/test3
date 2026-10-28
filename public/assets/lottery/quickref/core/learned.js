(function(root){
const E=root.LedgerEngine;
const key=s=>s.trim().replace(/\s+/g,' ').replace(/，/g,',');
const contextWords=/撤|取消|不要|改|照旧|加单|同上/;
const hit=/^(?:(体彩|排列三|排三|体|排|福彩3D|福彩|3[Dd])\s*)?(\d{1,9})\s*打\s*(\d+(?:\.\d+)?)\s*(?:元|块)?$/;
const gameFor=p=>/体彩|排列三|排三|体|排/.test(p||'')?'P3':'3D';
function build(entry){
  if(E.validate(entry)||entry.raw==='手动录入'||contextWords.test(entry.raw))return null;
  const sample={game:entry.game,play:entry.play,numbers:[...entry.numbers],amount:entry.amount,pos:entry.pos,includeBomb:!!entry.includeBomb};
  const rule={id:crypto.randomUUID(),updatedAt:Date.now(),raw:entry.raw,key:key(entry.raw),entry:sample,template:null,gameRuleVersion:2};
  const m=entry.raw.trim().match(hit);
  // Generalize only when the explicitly corrected numbers, amount and game
  // exactly agree with the two captures. Otherwise retain this exact example.
  if(m&&sample.numbers.length===1&&sample.numbers[0]===m[2]&&sample.amount===Number(m[3])&&sample.game===gameFor(m[1]))rule.template={kind:'hit',digits:m[2].length,prefix:m[1]||''};
  return rule;
}
function validRule(rule){return !!rule&&typeof rule.id==='string'&&typeof rule.raw==='string'&&typeof rule.key==='string'&&(rule.explanation===undefined||(typeof rule.explanation==='string'&&rule.explanation.length<=2000))&&rule.key===key(rule.raw)&&!contextWords.test(rule.raw)&&rule.entry&&!E.validate(rule.entry)&&(rule.entries===undefined||(Array.isArray(rule.entries)&&rule.entries.length>=2&&rule.entries.length<=20&&rule.entries.every(e=>!E.validate(e))&&JSON.stringify(rule.entries[0])===JSON.stringify(rule.entry)&&!rule.template))&&(!rule.template||(rule.template.kind==='hit'&&Number.isInteger(rule.template.digits)&&rule.template.digits>=1&&rule.template.digits<=9&&typeof rule.template.prefix==='string'))}
function remember(rules,entry){const r=build(entry);if(!r)return false;const old=rules.findIndex(x=>x.key===r.key);if(old>=0)rules.splice(old,1);rules.push(r);return true}
function rememberBundle(rules,entries){
  if(!Array.isArray(entries)||entries.length<2||entries.length>20||entries.some(e=>E.validate(e)||e.raw!==entries[0].raw))return false;
  const rule=build(entries[0]);if(!rule)return false;
  rule.template=null;rule.entries=entries.map(e=>({game:e.game,play:e.play,numbers:[...e.numbers],amount:e.amount,pos:e.pos,includeBomb:!!e.includeBomb}));
  if(!validRule(rule))return false;
  const old=rules.findIndex(r=>r.key===rule.key);if(old>=0)rules.splice(old,1);rules.push(rule);return true;
}
function defenseCorrection(raw,rates){
  if(typeof raw!=='string'||contextWords.test(raw))return null;
  const expression=/(\d{3,9})\s*[一—－-]*\s*(\d+(?:\.\d+)?)\s*(?:元|块)?\s*(?:放对子|放对|防对子|防对)\s*(\d+(?:\.\d+)?)\s*(?:元|块)?/g;
  const matches=[...raw.matchAll(expression)];if(!matches.length||matches.length>10)return null;
  const rest=raw.replace(expression,'').replace(/(?:合计|总计|共计|计)\s*\d+(?:\.\d+)?\s*(?:元|块)?/g,'').replace(/福彩3D|福彩|3[Dd]|体彩|排列三|排三|福|体|排/g,'').replace(/[\s一—－\-，,。.;；、：:]/g,'');
  if(rest)return null;
  let game='3D',cursor=0;const entries=[];
  for(const match of matches){
    const prefix=raw.slice(cursor,match.index),markers=[...prefix.matchAll(/福彩3D|福彩|3[Dd]|体彩|排列三|排三|福|体|排/g)];
    if(markers.length)game=gameFor(markers.at(-1)[0]);cursor=match.index+match[0].length;
    const number=match[1];
    for(const [kind,amount] of [['g6_',Number(match[2])],['g3_',Number(match[3])]]){
      const play=kind+number.length,e={game,play,numbers:[number],amount,pos:'',includeBomb:false,rate:rates[play],raw,status:'confirmed',reason:'',customer:''};
      if(E.validate(e)||!Number.isFinite(e.rate)||e.rate<=0)return null;entries.push(e);
    }
  }
  return entries;
}
// Only suggest complete, narrowly defined records. The user must confirm
// suggestions for an existing pending entry before any stakes are booked.
function knownCorrection(raw,rates){
  const defense=defenseCorrection(raw,rates);if(defense)return defense;
  if(typeof raw!=='string'||contextWords.test(raw))return null;
  const lines=raw.trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  let cursor=0;
  if(lines[cursor]&&/^[\u4e00-\u9fffA-Za-z_]{1,24}$/.test(lines[cursor])&&!/福|体|排|组|直|定位|独胆|独旦/.test(lines[cursor]))cursor++;
  if(lines[cursor]&&/^\d{4}(?:年\d{1,2}月\d{1,2}日|[-/]\d{1,2}[-/]\d{1,2})\s+\d{1,2}:\d{2}(?::\d{2})?$/.test(lines[cursor]))cursor++;
  let text=lines.slice(cursor).join('\n'),game=E.gameForRecord(raw)||'3D';
  const marker=text.match(/^(福彩3D|福彩|3[Dd]|福|体彩|排列三|排三|体|排)\s*/);
  if(marker){game=gameFor(marker[1]);text=text.slice(marker[0].length).trim()}
  const triple=text.match(/^(\d{3})\s*-{3}\s*(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)(?:\s*-\s*(\d+(?:\.\d+)?))?\s*$/);
  if(triple){
    const n=triple[1],entries=[['direct',Number(triple[2])],['g6_3',Number(triple[3])],...(triple[4]===undefined?[]:[['g3_3',Number(triple[4])]])].map(([play,amount])=>({game,play,numbers:[n],amount,pos:'',includeBomb:false,rate:rates[play],raw,status:'confirmed',reason:'',customer:''}));
    return entries.some(e=>E.validate(e)||!Number.isFinite(e.rate)||e.rate<=0)?null:entries;
  }
  const single=text.match(/^(?:独胆|独旦|一码不定位|不定位一码)\s*([0-9])\s*(?:打|各打|各)?\s*(\d+(?:\.\d+)?)\s*(?:元|块|倍)?$/);
  if(single){const e={game,play:'one',numbers:[single[1]],amount:Number(single[2]),pos:'',includeBomb:false,rate:rates.one,raw,status:'confirmed',reason:'',customer:''};return !E.validate(e)&&e.rate>0?[e]:null}
  const rows=text.split(/\r?\n/),entries=[];
  if(!rows.length||rows.length>10)return null;
  for(const row of rows){
    const m=row.match(/^(\d{4,9})\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)$/);if(!m)return null;
    for(const [kind,amount] of [['g6_',Number(m[2])],['g3_',Number(m[3])]]){
      const play=kind+m[1].length,e={game,play,numbers:[m[1]],amount,pos:'',includeBomb:false,rate:rates[play],raw,status:'confirmed',reason:'',customer:''};
      if(E.validate(e)||!Number.isFinite(e.rate)||e.rate<=0)return null;entries.push(e);
    }
  }
  return entries;
}
function recognize(raw,rates,rules){
  const normal=E.prepareAdd(raw,rates);if(normal.every(e=>e.status==='confirmed'))return normal;
  if(contextWords.test(raw))return normal;
  const reversed=[...rules].reverse(),exact=reversed.find(r=>r.key===key(raw));let rule=exact,numbers,amount;
  if(!rule){const m=raw.trim().match(hit);if(m){rule=reversed.find(r=>r.template?.kind==='hit'&&r.template.digits===m[2].length&&r.template.prefix===(m[1]||''));if(rule){numbers=[m[2]];amount=Number(m[3])}}}
  if(!rule)return normal;
  if(rule.entries){const entries=rule.entries.map(sample=>({...sample,numbers:[...sample.numbers],rate:rates[sample.play],raw,status:'confirmed',reason:'',customer:'',learned:true}));return entries.some(e=>E.validate(e)||!Number.isFinite(e.rate)||e.rate<=0)?normal:entries}
  const e={...rule.entry,numbers:numbers||[...rule.entry.numbers],amount:amount??rule.entry.amount,rate:rates[rule.entry.play],raw,status:'confirmed',reason:'',customer:'',learned:true};
  if(E.validate(e)||!Number.isFinite(e.rate)||e.rate<=0)return normal;
  return [e];
}
root.LedgerLearning={build,remember,rememberBundle,defenseCorrection,knownCorrection,recognize,validRule};
})(globalThis);
