(function(root){
function readCompletedSSE(text){
  let completed=null;const items=new Map(),textParts=new Map();let refused=false;
  for(const frame of text.split(/\r?\n\r?\n/)){
    const data=frame.split(/\r?\n/).filter(line=>line.startsWith('data:')).map(line=>line.slice(5).trimStart()).join('\n');
    if(!data||data==='[DONE]')continue;
    const event=JSON.parse(data);
    if(event.type==='response.output_item.done'&&event.item)items.set(event.output_index,event.item);
    if(event.type==='response.output_text.delta'||event.type==='response.output_text.done'){
      const key=String(event.output_index??0)+':'+String(event.content_index??0);
      textParts.set(key,event.type==='response.output_text.done'?event.text:(textParts.get(key)||'')+(event.delta||''));
    }
    if(event.type==='response.refusal.delta'||event.type==='response.refusal.done')refused=true;
    if(event.type==='response.failed'||event.type==='error'){
      const code=event.response?.error?.code||event.error?.code||event.code;
      if(code==='subscription_sharing_usage_limit_exceeded')throw Error('ChatGPT 套餐本时段额度已用完，请到 ChatGPT 查看恢复时间；原文已保留，本次未添加');
      if(code==='subscription_sharing_usage_unavailable')throw Error('当前账号暂不能使用套餐识别，请到 ChatGPT 检查套餐及应用权限；原文已保留，本次未添加');
      throw Error('套餐模型未能完成识别；原文已保留，本次未添加');
    }
    if(event.type==='response.incomplete')throw Error('模型分析未完整结束，请分批粘贴；原文已保留，本次未添加');
    if(event.type==='response.completed')completed=event.response;
  }
  if(!completed||completed.status!=='completed')throw Error('模型连接中断，未收到完整结果；原文已保留，本次未添加');
  if(refused)throw Error('模型无法识别这条记录，请补充具体说明；原文已保留，本次未添加');
  // Some plan streams deliver text in delta/done events and omit it from the
  // final response object. Only release collected text after completion.
  if(!Array.isArray(completed.output)||!completed.output.length)completed.output=[...items.entries()].sort(([a],[b])=>a-b).map(([,item])=>item);
  const existing=(completed.output||[]).flatMap(item=>item.content||[]).filter(part=>part.type==='output_text').map(part=>part.text||'').join('');
  if(!existing&&textParts.size)completed.output=[...(completed.output||[]),{type:'message',content:[{type:'output_text',text:[...textParts.entries()].sort(([a],[b])=>{const aa=a.split(':').map(Number),bb=b.split(':').map(Number);return aa[0]-bb[0]||aa[1]-bb[1]}).map(([,value])=>value||'').join('')}]}];
  return completed;
}

root.LotteryModuleStream={readCompletedSSE};
})(globalThis);
