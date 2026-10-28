(function(root){
const E=root.LedgerEngine;
const entryProperties={game:{type:'string',enum:['3D','P3']},play:{type:'string',enum:Object.keys(E.defs)},numbers:{type:'array',items:{type:'string'}},amount:{type:'number'},pos:{type:'string',enum:['','0','1','2','01','02','12']},includeBomb:{type:'boolean'}};
const schema={type:'object',properties:{segments:{type:'array',items:{type:'object',properties:{raw:{type:'string'},question:{type:'string'},entries:{type:'array',items:{type:'object',properties:entryProperties,required:Object.keys(entryProperties),additionalProperties:false}}},required:['raw','question','entries'],additionalProperties:false}}},required:['segments'],additionalProperties:false};
const compact=s=>s.replace(/\s/g,'');
function validClarification(c){return c===undefined||(!!c&&typeof c.question==='string'&&c.question.length<=4000&&typeof c.answer==='string'&&!!c.answer.trim()&&c.answer.length<=2000&&Array.isArray(c.proposal)&&c.proposal.length<=100&&c.proposal.every(e=>!E.validate(e)))}
function normalize(result,raw,rates,rules=[]){
  if(!result||!Array.isArray(result.segments)||!result.segments.length||result.segments.length>500)throw Error('模型没有返回完整单子，请重试');
  if(result.segments.some(s=>typeof s.raw!=='string'||!s.raw.trim()||typeof s.question!=='string'||!Array.isArray(s.entries)||s.entries.length>500))throw Error('模型返回格式不完整，请重试');
  // Every character of the input must remain assigned to one segment in order.
  // This prevents omitted records and invented or silently rewritten messages.
  if(compact(result.segments.map(s=>s.raw).join(''))!==compact(raw))throw Error('模型遗漏或改写了部分原文，本次未添加，请重试');
  const entries=[];
  for(const s of result.segments){
    const key=s.raw.trim().replace(/\s+/g,' ').replace(/，/g,',');
    const remembered=Array.isArray(rules)?rules.find(r=>root.LedgerLearning?.validRule(r)&&r.key===key):null;
    const normalized=(remembered?(remembered.entries||[remembered.entry]):s.entries).map(e=>({customer:'',game:remembered&&remembered.gameRuleVersion!==2&&E.gameForRecord(s.raw)==='P3'?'P3':e.game,play:e.play,numbers:[...e.numbers],amount:e.amount,pos:e.pos,includeBomb:e.includeBomb,rate:rates[e.play],raw:s.raw,status:'confirmed',reason:'',source:remembered?'memory':'model'}));
    const invalid=normalized.map(e=>E.validate(e)||(e.play!=='sum'&&(!Number.isFinite(e.rate)||e.rate<=0)?'赔率无效':'')).find(Boolean);
    const question=(remembered?'':s.question.trim())||invalid||(!normalized.length?'未能确定这段原文的投注内容，请补充说明':'');
    if(question){entries.push({customer:'',game:normalized[0]?.game||'3D',play:'',numbers:[],amount:0,pos:'',includeBomb:false,rate:0,raw:s.raw,status:'pending',reason:question,source:'model',suggestedEntries:!invalid&&normalized.length<=100?normalized:[]});continue}
    entries.push(...normalized);
  }
  if(entries.length>1000)throw Error('单子太多，请分批粘贴');
  return entries;
}
root.LedgerModel={schema,normalize,validClarification};if(typeof module!=='undefined')module.exports=root.LedgerModel;
})(globalThis);
