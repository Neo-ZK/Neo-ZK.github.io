---
layout: post
title: 聊一聊针对RSA算法的Bleichenbacher攻击
categories: Network
keywords: TLS, RSA, Network
---

## 背景       
RSA算法是互联网安全中非常具有代表性的算法之一，然而近些年却越来越淡出主舞台了，究其原因，一是其性能确实相对较低，二是不断爆出的安全漏洞也令其饱受困扰，今天就来聊一聊针对RSA算法的Bleichenbacher攻击模式


## 背景知识
要较为深入的了解这一种攻击模式需要两方面的背景知识，一是RSA算法的基本原理，二是RSA的PKCS#1 v1.5填充模式，下面对这两方面的知识做一个详细的阐述

### RSA算法的基本原理
这两篇文章对于RSA算法的数学原理讲的非常清晰和透彻：[RSA的数学背景知识](http://www.ruanyifeng.com/blog/2013/06/rsa_algorithm_part_one.html?spm=ata.21736010.0.0.6ff25c18D2agzb)，[RSA的原理](http://www.ruanyifeng.com/blog/2013/07/rsa_algorithm_part_two.html?spm=ata.21736010.0.0.6ff25c18D2agzb)，如果对于过多的数学推导不太感冒，那也可以直接看我下面的说明，我们尽可能的省略掉相关的数学公式。

首先我们需要明确的是RSA的功能：生成一对公私钥，用公钥加密的东西可以用私钥解密(对应握手过程)，私钥加密的东西也可以用公钥解密(对应签名过程)，而实际上公私钥只是一个很大的数字而已。整个加解密的流程可以用如下流程表示：

- 定义公钥为(n,e)，私钥为(n,d),其中n为两个大质数p,q相乘的结果，即n=p*q
- 定义消息为m
- 定义加密过程为m<sup>e</sup>  %  n = c ，其中%表示求余预算，而c则是传输的密文
- 定义解密过程为cd  %  n = m

稍微带点证明的前缀知识(后面会用到)：对于密文c，我们实际可以写作c =m<sup>e</sup> - kn，证明解密过程实际等于证明(m<sup>e</sup> - kn)d % n = m，将多项式展开，实际等于证明m<sup>ed</sup> % n = m

RSA的安全性建立在：当p,q为大质数时，计算n很容易，但中间人拿到n时计算p,q很难。至少到目前为止这个数学流程的安全性仍有相当的保障，但为什么我们又说RSA的安全性不高呢？我们来看加密过程，由于n,e在生成公私钥对时就已经是确定的值了，那么m不变意味着c就不变，当m的长度不够时，中间人是可能通过暴力破解的方式试出m的值的，因此，在实际的RSA使用中会遵循PKCS标准来对消息进行扩展，而本文介绍的Bleichenbacher攻击主要与PKCS#1 v1.5的rsa加密标准相关，因此下面仅介绍这种padding模式，该模式下一个padding block主要长这样：

![](/images/self-drawn/rsa_attack/padding.png)

可以看到，整个padding block由5部分组成，PKCS#1 v1.5规定整个padding block的长度定义为K字节，其中K为前面提到的大数n所占的字节数，定义data block的数据长度为D字节，padding string不小于8字节，即D不能超过K-1-1-8-1 = K-11个字节。最终再把这个data block转换成一个大数x作为消息加密发送。

到目前看来，在PKCS机制的保护下，RSA的安全性更进了一步，那么Bleichenbacher攻击又是从何种角度入手来对进行攻击的呢？原因就出在padding block的前两个字节上，下面我们来详细分析下Bleichenbacher的攻击模式：

- 中间人首先拿到client发出的密文c，我们知道c = m<sup>e</sup> % n
- 中间人随机挑选一个数s，然后将截获到的c进行: c' = c * (s<sup>e</sup>) % n进行发送
- server拿到c'然后开始解密：m' = (c')<sup>d</sup> % n = (c * (s<sup>e</sup>))d % n = [(c<sup>d</sup> % n) * (s<sup>ed</sup> % n)] % n
- 由上文我们提到的rsa解密过程我们知道c<sup>d</sup> % n = m，s<sup>ed</sup> % n = s，于是式子可以写作：m' = (m * s )% n

在实际的tls协议中，当解出的消息m'的格式不符合PKCS标准，即前两个字节不为0x00,0x02时，会返回一个不符合PKCS#1标准的错误，也就是说，中间人如果不断尝试s，如果没有收到这个错误，那么就可以得到m*s的前两个字节为0x00,0x02。中间人持续不断的尝试s的值，最终是有可能将消息m试出来的，关于Bleichenbacher攻击可行性的数学分析可以看[这篇论文](https://archiv.infsec.ethz.ch/education/fs08/secsem/Bleichenbacher98.pdf?spm=ata.21736010.0.0.6ff25c18D2agzb&file=Bleichenbacher98.pdf)，至此，这种攻击模式的分析就全部结束了。当然，应对攻击的方式也很简单，server不再针对不符合PKCS#1 v1.5的标准单独返回一个错误，而是统一返回握手失败的错误。(由于rsa算法本身的安全性，中间人是无法通过篡改的消息c'完成完整的tls握手的，server拿不到正确的消息m，无法生成对应的对称密钥，最终导致握手失败)